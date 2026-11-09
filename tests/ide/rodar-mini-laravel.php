<?php
/* Roda o mini-Laravel do navegador (public/js/ide-mini-laravel.txt) no PHP do computador, para os testes.
   Uso: php rodar-mini-laravel.php <pasta do projeto> <arquivo .json com a requisição>  ->  JSON da resposta */
$req = json_decode((string) file_get_contents($argv[2]), true) ?: [];
$req['base'] = rtrim(str_replace('\\', '/', $argv[1]), '/') . '/';
$GLOBALS['__wf_ob'] = ob_get_level();
require __DIR__ . '/../../public/js/ide-mini-laravel.txt';
echo json_encode(\WF\principal($req), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
