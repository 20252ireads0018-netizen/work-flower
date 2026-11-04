<!doctype html>
<html lang="pt-BR">
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;border-top:4px solid {{ preg_match('/^#[0-9a-f]{6}$/i', $evento->cor) ? $evento->cor : '#2f6fc7' }};">
        <p style="margin:0 0 4px;font-size:13px;color:#71717a;">Lembrete do seu calendário</p>
        <h1 style="margin:0 0 12px;font-size:22px;">{{ $evento->titulo }}</h1>
        <p style="margin:0 0 12px;font-size:15px;"><b>{{ $quando }}</b></p>
        @if ($evento->descricao)
            <p style="margin:0 0 16px;font-size:14px;white-space:pre-line;">{{ $evento->descricao }}</p>
        @endif
        <a href="{{ $link }}" style="display:inline-block;background:#2f6fc7;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;font-size:14px;">Abrir o painel</a>
        <p style="margin:20px 0 0;font-size:12px;color:#a1a1aa;">Work Flower · para parar de receber, desmarque "Avisar por e-mail" no evento.</p>
    </div>
</body>
</html>
