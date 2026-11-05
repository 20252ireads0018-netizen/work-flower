<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Candidaturas · Work Flower</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 0; padding: 24px; background: #101612; color: #ebf1ed; }
  h1 { font-size: 1.4rem; margin: 0 0 4px; }
  p { color: #9baba1; margin: 0 0 16px; }
  a { color: #f08a9b; }
  .scroll { overflow-x: auto; }
  table { border-collapse: collapse; width: 100%; font-size: 14px; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #2c3931; vertical-align: top; }
  th { color: #9baba1; font-weight: 600; white-space: nowrap; }
</style>
</head>
<body>
  <h1>Candidaturas</h1>
  <p>{{ $candidaturas->count() }} registro(s) ·
     <a href="{{ request()->fullUrlWithQuery(['formato' => 'csv']) }}">Baixar CSV</a></p>
  <div class="scroll">
    <table>
      <thead><tr>
        <th>Data</th><th>Nome</th><th>E-mail</th><th>WhatsApp</th><th>Cidade</th><th>Escolaridade</th>
        <th>Experiência</th><th>Disponibilidade</th><th>Mensagem</th><th>Curso</th>
      </tr></thead>
      <tbody>
      @forelse ($candidaturas as $c)
        <tr>
          <td>{{ $c->created_at?->format('d/m/Y H:i') }}</td>
          <td>{{ $c->nome }}</td>
          <td>{{ $c->email }}</td>
          <td>{{ $c->fone }}</td>
          <td>{{ $c->cidade }}</td>
          <td>{{ $c->escolaridade }}</td>
          <td>{{ $c->exp }}</td>
          <td>{{ implode(', ', $c->dias) }} · {{ $c->turno }} · {{ $c->horas }} h/sem</td>
          <td>{{ $c->msg }}</td>
          <td>{{ $c->curso_clicado_em ? 'clicou em '.$c->curso_clicado_em->format('d/m H:i') : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="10">Nenhuma candidatura ainda.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</body>
</html>
