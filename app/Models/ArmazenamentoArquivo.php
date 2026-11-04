<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagem ou PDF que saiu do banco/disco do site e foi para o destino da conta.
 * Imagens: a linha img.* passa a guardar "/armazenamento/arquivo/{token}" no lugar do data URL.
 * PDFs da Mente: o arquivo local é apagado e /pdf/{id} busca no destino.
 */
class ArmazenamentoArquivo extends Model
{
    protected $table = 'armazenamento_arquivos';

    protected $fillable = ['user_id', 'destino_id', 'token', 'tipo', 'tabela', 'chave', 'remoto', 'mime', 'tamanho'];

    public function destino(): BelongsTo
    {
        return $this->belongsTo(ArmazenamentoDestino::class, 'destino_id');
    }

    /** Endereço que fica no lugar da imagem (relativo, funciona em qualquer domínio). */
    public static function url(string $token): string
    {
        return '/armazenamento/arquivo/' . $token;
    }

    /** O que fica gravado na linha img.* (JSON de uma string, como o JSON.stringify do quadro grava). */
    public static function valor(string $token): string
    {
        return json_encode(self::url($token), JSON_UNESCAPED_SLASHES);
    }
}
