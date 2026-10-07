<?php

namespace Tests\Unit;

use App\Jobs\ProcessAquarela;
use Tests\TestCase;

class AquarelaLinkTest extends TestCase
{
    public function test_usa_a_pagina_publica_pelo_id(): void
    {
        $this->assertSame(
            'https://aquarela.app.br/rea/286',
            ProcessAquarela::linkPublico('https://aquarelaapi.dev.br/api/reas/286', 286)
        );
    }

    public function test_converte_link_da_api_gravado_em_buscas_antigas(): void
    {
        $this->assertSame('https://aquarela.app.br/rea/1210', ProcessAquarela::linkPublico('https://aquarelaapi.dev.br/api/reas/1210'));
    }

    public function test_mantem_link_que_nao_e_da_api(): void
    {
        $this->assertSame('https://exemplo.org/recurso', ProcessAquarela::linkPublico('https://exemplo.org/recurso'));
        $this->assertNull(ProcessAquarela::linkPublico(null));
    }

    public function test_site_configuravel(): void
    {
        config(['app.aquarela.site' => 'https://outro.aquarela.test/']);

        $this->assertSame('https://outro.aquarela.test/rea/7', ProcessAquarela::linkPublico(null, 7));
    }
}
