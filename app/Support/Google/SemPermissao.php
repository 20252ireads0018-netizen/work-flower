<?php

namespace App\Support\Google;

use RuntimeException;

/** O Google recusou porque falta um escopo opcional (ex.: editar o canal do YouTube): o cartão mostra "Permitir editar". */
class SemPermissao extends RuntimeException
{
}
