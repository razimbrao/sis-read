<?php

namespace Tests\Unit;

use App\Jobs\ProcessMecRed;
use Tests\TestCase;

class MecRedLinkTest extends TestCase
{
    public function test_monta_a_pagina_publica_pelo_id(): void
    {
        $this->assertSame('https://mecred.mec.gov.br/recurso/367613', ProcessMecRed::linkPublico(367613));
    }

    public function test_sem_id_nao_ha_link(): void
    {
        $this->assertNull(ProcessMecRed::linkPublico(null));
        $this->assertNull(ProcessMecRed::linkPublico(''));
    }

    public function test_site_configuravel(): void
    {
        config(['app.mecred.site' => 'https://outro.mecred.test/']);

        $this->assertSame('https://outro.mecred.test/recurso/7', ProcessMecRed::linkPublico(7));
    }
}
