<?php

namespace Tests\Unit;

use App\Support\Armazenamento\GoogleDrive;
use App\Support\Emails\Google;
use App\Support\Google\Youtube;
use Tests\TestCase;

class GoogleRelayTest extends TestCase
{
    public function test_sem_repasse_usa_as_rotas_do_site(): void
    {
        config(['services.google.relay' => '', 'services.google.redirect' => null]);

        $this->assertSame(route('armazenamento.google.retorno'), GoogleDrive::retorno());
        $this->assertSame(route('emails.google.retorno'), Google::retorno());
        $this->assertSame(route('youtube.google.retorno'), Youtube::retorno());
    }

    public function test_com_repasse_usa_a_pagina_do_github(): void
    {
        config(['services.google.relay' => 'https://exemplo.github.io/work-flower/google', 'services.google.redirect' => null]);

        $this->assertSame('https://exemplo.github.io/work-flower/google/drive/', GoogleDrive::retorno());
        $this->assertSame('https://exemplo.github.io/work-flower/google/emails/', Google::retorno());
        $this->assertSame('https://exemplo.github.io/work-flower/google/youtube/', Youtube::retorno());
        $this->assertStringContainsString(rawurlencode('https://exemplo.github.io/work-flower/google/drive/'), GoogleDrive::urlAutorizacao('x', GoogleDrive::retorno()));
    }
}
