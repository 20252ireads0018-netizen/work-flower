<?php

namespace App\Support\Emails;

/**
 * Cliente IMAP mínimo em PHP puro (stream_socket_client + openssl), sem a extensão imap nem bibliotecas:
 * funciona na hospedagem compartilhada sem precisar de composer. Só lê: abre a pasta com EXAMINE
 * (somente leitura) e busca com BODY.PEEK, então nada é marcado como lido na caixa da pessoa.
 */
class ImapCliente
{
    /** @var resource|null */
    private $conexao = null;
    private int $tag = 0;

    public function __construct(private int $tempoLimite = 20)
    {
    }

    /** Abre a conexão. $seguranca: "ssl" (porta 993) ou "starttls" (porta 143). */
    public function conectar(string $host, int $porta, string $seguranca = 'ssl'): void
    {
        $ip = self::ipPublico($host);
        $contexto = stream_context_create(['ssl' => [
            'peer_name'         => $host,
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'SNI_enabled'       => true,
        ]]);
        $esquema = $seguranca === 'ssl' ? 'ssl' : 'tcp';
        $alvo = $esquema . '://' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip) . ':' . $porta;

        $erroNum = 0;
        $erroTexto = '';
        $c = @stream_socket_client($alvo, $erroNum, $erroTexto, $this->tempoLimite, STREAM_CLIENT_CONNECT, $contexto);
        if (! $c) {
            throw new EmailErro("Não consegui conectar em {$host}:{$porta}. Confira o servidor, a porta e a segurança (SSL/STARTTLS).");
        }
        stream_set_timeout($c, $this->tempoLimite);
        $this->conexao = $c;

        $saudacao = $this->linha();
        if (! str_starts_with($saudacao, '* OK') && ! str_starts_with($saudacao, '* PREAUTH')) {
            $this->fechar();
            throw new EmailErro('O servidor não respondeu como um servidor IMAP.');
        }

        if ($seguranca === 'starttls') {
            $this->comando('STARTTLS');
            if (! @stream_socket_enable_crypto($c, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->fechar();
                throw new EmailErro('Não consegui ligar a criptografia (STARTTLS) com o servidor.');
            }
        }
    }

    /** Usa uma conexão já aberta (os testes passam um par de sockets com as respostas do servidor). */
    public function usarConexao($stream): void
    {
        $this->conexao = $stream;
    }

    public function entrar(string $usuario, string $senha): void
    {
        try {
            $this->comando('LOGIN ' . self::texto($usuario) . ' ' . self::texto($senha));
        } catch (EmailErro $e) {
            throw new EmailErro('O servidor recusou o usuário ou a senha. No Gmail, use uma senha de app (não a senha normal da conta).');
        }
    }

    /** Entra com OAuth2 (XOAUTH2), usado pelo Gmail quando conectado pelo Google. */
    public function entrarOAuth(string $usuario, string $token): void
    {
        $sasl = base64_encode("user={$usuario}\x01auth=Bearer {$token}\x01\x01");
        try {
            $this->comando('AUTHENTICATE XOAUTH2 ' . $sasl);
        } catch (EmailErro $e) {
            throw new EmailErro('O Google recusou o acesso. Conecte a conta de novo.');
        }
    }

    /** Abre a pasta só para leitura. Devolve o UIDVALIDITY (muda quando a pasta é recriada no servidor). */
    public function examinar(string $pasta = 'INBOX'): ?string
    {
        [, $respostas] = $this->comando('EXAMINE ' . self::texto($pasta));
        foreach ($respostas as $r) {
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $r['texto'], $m)) return $m[1];
        }

        return null;
    }

    /** @return list<int> UIDs das mensagens recebidas desde a data */
    public function buscarDesde(\DateTimeInterface $desde): array
    {
        $data = $desde->format('j') . '-' . ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][(int) $desde->format('n') - 1] . '-' . $desde->format('Y');
        [, $respostas] = $this->comando('UID SEARCH SINCE ' . $data);
        $uids = [];
        foreach ($respostas as $r) {
            if (preg_match('/^\* SEARCH\b(.*)$/i', trim($r['texto']), $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) as $n) if (ctype_digit($n)) $uids[] = (int) $n;
            }
        }
        sort($uids);

        return $uids;
    }

    /** @return array<int, bool> UID => lido (\Seen) */
    public function lidos(array $uids): array
    {
        $saida = [];
        foreach (array_chunk($uids, 300) as $lote) {
            [, $respostas] = $this->comando('UID FETCH ' . implode(',', $lote) . ' (UID FLAGS)');
            foreach ($respostas as $r) {
                if (preg_match('/\bUID (\d+)/i', $r['texto'], $u) && preg_match('/FLAGS \(([^)]*)\)/i', $r['texto'], $f)) {
                    $saida[(int) $u[1]] = stripos($f[1], '\\Seen') !== false;
                }
            }
        }

        return $saida;
    }

    /**
     * Cabeçalhos e começo do corpo de cada UID (sem marcar como lido).
     *
     * @return list<array{uid: int, lido: bool, data: ?string, cabecalho: string, corpo: string}>
     */
    public function cabecalhos(array $uids, int $bytesCorpo = 4000): array
    {
        $saida = [];
        foreach (array_chunk($uids, 50) as $lote) {
            [, $respostas] = $this->comando('UID FETCH ' . implode(',', $lote)
                . ' (UID FLAGS INTERNALDATE BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE MESSAGE-ID CONTENT-TYPE CONTENT-TRANSFER-ENCODING)] BODY.PEEK[TEXT]<0.' . $bytesCorpo . '>)');
            foreach ($respostas as $r) {
                $t = $r['texto'];
                if (! preg_match('/^\* \d+ FETCH/i', $t) || ! preg_match('/\bUID (\d+)/i', $t, $u)) continue;
                preg_match('/FLAGS \(([^)]*)\)/i', $t, $f);
                preg_match('/INTERNALDATE "([^"]+)"/i', $t, $d);
                $saida[] = [
                    'uid'       => (int) $u[1],
                    'lido'      => isset($f[1]) && stripos($f[1], '\\Seen') !== false,
                    'data'      => $d[1] ?? null,
                    'cabecalho' => self::valorDe($t, $r['literais'], 'BODY\[HEADER\.FIELDS[^\]]*\]'),
                    'corpo'     => self::valorDe($t, $r['literais'], 'BODY\[TEXT\](?:<\d+>)?'),
                ];
            }
        }

        return $saida;
    }

    public function sair(): void
    {
        if (! $this->conexao) return;
        try {
            $this->comando('LOGOUT');
        } catch (\Throwable) {
        }
        $this->fechar();
    }

    public function fechar(): void
    {
        if ($this->conexao) @fclose($this->conexao);
        $this->conexao = null;
    }

    /* ---------------- protocolo ---------------- */

    /**
     * Manda um comando e lê até a resposta com a etiqueta dele. Cada resposta "*" vem com o texto
     * (literais {n} trocados por marcadores \x00L0\x00, \x00L1\x00...) e a lista dos literais.
     *
     * @return array{0: string, 1: list<array{texto: string, literais: list<string>}>}
     */
    private function comando(string $cmd): array
    {
        if (! $this->conexao) throw new EmailErro('Sem conexão com o servidor de e-mail.');
        $tag = 'W' . (++$this->tag);
        if (@fwrite($this->conexao, $tag . ' ' . $cmd . "\r\n") === false) {
            throw new EmailErro('A conexão com o servidor de e-mail caiu.');
        }

        $respostas = [];
        while (true) {
            [$texto, $literais] = $this->resposta();
            if (str_starts_with($texto, $tag . ' ')) {
                $resto = substr($texto, strlen($tag) + 1);
                if (! preg_match('/^OK\b/i', $resto)) {
                    throw new EmailErro('O servidor de e-mail respondeu: ' . mb_substr(trim($resto), 0, 150));
                }

                return [$resto, $respostas];
            }
            if (str_starts_with($texto, '+')) { // pedido de continuação (ex.: erro do XOAUTH2): responde vazio
                @fwrite($this->conexao, "\r\n");
                continue;
            }
            $respostas[] = ['texto' => $texto, 'literais' => $literais];
        }
    }

    /** Uma resposta completa, juntando os literais {n} que vêm no meio da linha. */
    private function resposta(): array
    {
        $texto = '';
        $literais = [];
        while (true) {
            $l = $this->linha();
            if (preg_match('/\{(\d+)\}$/', $l, $m)) {
                $texto .= substr($l, 0, -strlen($m[0])) . "\x00L" . count($literais) . "\x00";
                $literais[] = $this->bytes((int) $m[1]);
                continue;
            }

            return [$texto . $l, $literais];
        }
    }

    private function linha(): string
    {
        $l = fgets($this->conexao);
        if ($l === false) {
            $meta = stream_get_meta_data($this->conexao);
            throw new EmailErro(! empty($meta['timed_out']) ? 'O servidor de e-mail demorou demais para responder.' : 'A conexão com o servidor de e-mail caiu.');
        }

        return rtrim($l, "\r\n");
    }

    private function bytes(int $n): string
    {
        $s = '';
        while (strlen($s) < $n) {
            $p = fread($this->conexao, min(65536, $n - strlen($s)));
            if ($p === false || $p === '') {
                if (feof($this->conexao) || ! empty(stream_get_meta_data($this->conexao)['timed_out'])) {
                    throw new EmailErro('A conexão com o servidor de e-mail caiu no meio da leitura.');
                }
                continue;
            }
            $s .= $p;
        }

        return $s;
    }

    /** Valor de um item do FETCH: literal, "texto entre aspas" ou NIL. */
    private static function valorDe(string $texto, array $literais, string $chave): string
    {
        if (! preg_match('/' . $chave . '\s+(?:\x00L(\d+)\x00|"((?:[^"\\\\]|\\\\.)*)"|NIL)/i', $texto, $m)) return '';
        if (isset($m[1]) && $m[1] !== '') return $literais[(int) $m[1]] ?? '';

        return isset($m[2]) ? stripcslashes($m[2]) : '';
    }

    /** "texto" com aspas e barras escapadas; com literal quando tem quebra de linha ou não é ASCII. */
    private static function texto(string $s): string
    {
        if (preg_match('/[\r\n]/', $s)) throw new EmailErro('Usuário ou senha com quebra de linha.');

        return '"' . addcslashes($s, "\\\"") . '"';
    }

    /**
     * Resolve o servidor e recusa endereços internos (127.x, 10.x, 192.168.x...), para ninguém usar o site
     * para sondar a rede do próprio servidor.
     */
    public static function ipPublico(string $host): string
    {
        if (! preg_match('/^(?=.{1,190}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', $host)) {
            throw new EmailErro('Servidor inválido. Use um nome como imap.titan.email.');
        }
        $ips = @gethostbynamel($host) ?: [];
        if (! $ips) throw new EmailErro("Não encontrei o servidor {$host}. Confira o nome.");
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new EmailErro('Esse servidor aponta para um endereço interno e não pode ser usado.');
            }
        }

        return $ips[0];
    }
}
