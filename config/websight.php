<?php

/*
 * Outros sites da Websight, para a tela /admin/sites-websight (quem do Work Flower também se cadastrou neles).
 * Só leitura: procura o e-mail de cada pessoa na tabela de cadastro de cada site.
 *
 * WEBSIGHT_<SITE>_DB aceita:
 *  - o caminho do arquivo SQLite do site (ex.: /home/conta/websight.com.br/saas-ged/database/database.sqlite);
 *  - uma URL do MySQL (ex.: mysql://usuario:senha@localhost:3306/banco). O usuário só precisa de SELECT.
 * Site sem WEBSIGHT_<SITE>_DB aparece como "não configurado".
 */
return [
    'sites' => [
        'praga' => [
            'nome' => 'Praga Vermelha', 'url' => env('WEBSIGHT_PRAGA_URL'),
            'db' => env('WEBSIGHT_PRAGA_DB'), 'tabela' => 'users', 'coluna' => 'email',
        ],
        'worldbuilder' => [
            'nome' => 'Worldbuilder', 'url' => env('WEBSIGHT_WORLDBUILDER_URL'),
            'db' => env('WEBSIGHT_WORLDBUILDER_DB'), 'tabela' => 'users', 'coluna' => 'email',
        ],
        // o Trabalhos não tem conta: vale quem já fez pedido com esse e-mail
        'trabalhos' => [
            'nome' => 'Trabalhos', 'url' => env('WEBSIGHT_TRABALHOS_URL'),
            'db' => env('WEBSIGHT_TRABALHOS_DB'), 'tabela' => 'pedidos', 'coluna' => 'email',
        ],
        'ged' => [
            'nome' => 'GED', 'url' => env('WEBSIGHT_GED_URL'),
            'db' => env('WEBSIGHT_GED_DB'), 'tabela' => 'users', 'coluna' => 'email',
        ],
        'agenda' => [
            'nome' => 'Agenda', 'url' => env('WEBSIGHT_AGENDA_URL'),
            'db' => env('WEBSIGHT_AGENDA_DB'), 'tabela' => 'users', 'coluna' => 'email',
        ],
        'cobrancas' => [
            'nome' => 'Cobranças', 'url' => env('WEBSIGHT_COBRANCAS_URL'),
            'db' => env('WEBSIGHT_COBRANCAS_DB'), 'tabela' => 'users', 'coluna' => 'email',
        ],
        'os' => [
            'nome' => 'Ordens de serviço', 'url' => env('WEBSIGHT_OS_URL'),
            'db' => env('WEBSIGHT_OS_DB'), 'tabela' => 'users', 'coluna' => 'email',
        ],
    ],
];
