<?php

namespace Tests\Feature;

use App\Experimento\Experimento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Os query params da URL acompanham o usuário entre as páginas.
 */
class ParametrosNaNavegacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_internos_levam_os_params_da_pagina(): void
    {
        $this->get('/?grupo=controle&utm_source=email')
            ->assertOk()
            ->assertSee(route('login').'?grupo=controle&utm_source=email')
            ->assertSee(route('register').'?grupo=controle&utm_source=email')
            ->assertSee(route('privacidade').'?grupo=controle&utm_source=email');
    }

    public function test_secao_nao_acompanha_e_a_do_link_tem_prioridade(): void
    {
        $tela = $this->get('/?secao=colaborar&utm_source=email')->assertOk();

        $tela->assertSee(route('login').'?utm_source=email')
            ->assertSee(url('/').'?utm_source=email&secao=sugestao');
    }

    public function test_pagina_sem_params_mantem_os_links_limpos(): void
    {
        $this->get('/')->assertOk()->assertSee('href="'.route('login').'"', false);
    }

    public function test_redirect_de_visitante_para_o_login_leva_os_params(): void
    {
        $this->get('/emapre?utm_source=email')->assertRedirect(route('login').'?utm_source=email');
    }

    public function test_logout_leva_os_params_da_pagina_de_origem(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeader('referer', url('/?utm_source=email'))
            ->post('/logout')
            ->assertRedirect(route('login').'?utm_source=email');
    }

    public function test_referer_de_outro_site_nao_passa_params(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeader('referer', 'https://outro.example/?utm_source=email')
            ->post('/logout')
            ->assertRedirect(route('login'));
    }

    public function test_grupo_que_acompanha_a_navegacao_nao_troca_o_da_conta(): void
    {
        $user = User::factory()->create();
        app(Experimento::class)->fixar('controle', $user);

        $this->get('/?grupo=transparencia')->assertOk();

        // Depois do login, o mesmo código continua na URL.
        $this->actingAs($user)->get('/?grupo=transparencia')->assertOk();

        $this->app->forgetScopedInstances();
        $this->assertSame('controle', app(Experimento::class)->grupo($user));
    }

    public function test_codigo_diferente_na_mesma_sessao_ainda_troca_o_grupo(): void
    {
        $this->get('/?grupo=transparencia')->assertOk();
        $this->get('/?grupo=controle')->assertOk();

        $this->app->forgetScopedInstances();
        $this->assertSame('controle', app(Experimento::class)->grupo());
    }
}
