<?php

namespace Tests\Feature;

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Tests\TestCase;

class CadastroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Como em produção: sem o inglês como rede de proteção, uma chave ausente aparece crua na tela.
        app()->setLocale('pt_BR');
        app('translator')->setFallback('pt_BR');
    }

    public function test_campos_vazios_mostram_mensagens_em_portugues(): void
    {
        $erros = Livewire::test(Register::class)->call('register')->errors();

        $this->assertSame('O campo nome é obrigatório.', $erros->first('name'));
        $this->assertSame('O campo e-mail é obrigatório.', $erros->first('email'));
        $this->assertSame('O campo senha é obrigatório.', $erros->first('password'));
    }

    public function test_senha_curta_ou_diferente_da_confirmacao_e_recusada(): void
    {
        $curta = str_repeat('a', Register::SENHA_MINIMA - 1);
        $valida = str_repeat('a', Register::SENHA_MINIMA);

        $erros = Livewire::test(Register::class)
            ->set(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => $curta, 'password_confirmation' => $curta])
            ->call('register')->errors();
        $this->assertSame('O campo senha deve ter pelo menos '.Register::SENHA_MINIMA.' caracteres.', $erros->first('password'));

        $erros = Livewire::test(Register::class)
            ->set(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => $valida, 'password_confirmation' => $valida.'b'])
            ->call('register')->errors();
        $this->assertSame('A confirmação do campo senha não confere.', $erros->first('password'));

        $this->assertSame(0, User::count());
    }

    public function test_senha_no_tamanho_minimo_cria_a_conta(): void
    {
        $senha = str_repeat('a', Register::SENHA_MINIMA);

        Livewire::test(Register::class)
            ->set(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => $senha, 'password_confirmation' => $senha])
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertAuthenticatedAs(User::firstWhere('email', 'ana@example.com'));
    }

    public function test_requisitos_da_senha_aparecem_antes_de_digitar(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Pelo menos '.Register::SENHA_MINIMA.' caracteres')
            ->assertSee('As duas senhas são iguais')
            // A regra do navegador usa o mesmo mínimo do servidor.
            ->assertSee('[...senha].length >= '.Register::SENHA_MINIMA);
    }

    public function test_traducao_tem_todas_as_chaves_do_framework(): void
    {
        $framework = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $projeto = require lang_path('pt_BR/validation.php');

        $faltando = array_diff(
            array_keys(Arr::dot(Arr::except($framework, ['custom', 'attributes']))),
            array_keys(Arr::dot($projeto)),
        );

        $this->assertSame([], array_values($faltando));
    }
}
