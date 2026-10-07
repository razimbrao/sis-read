<?php

namespace App\Console\Commands;

use App\Experimento\Experimento;
use App\Features\GrupoExperimento;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

/**
 * Consulta ou fixa o grupo de um usuário no experimento de explicabilidade (docs/feature-flags.md).
 */
class ExperimentoGrupo extends Command
{
    protected $signature = 'experimento:grupo
        {usuario? : id ou e-mail do usuário}
        {grupo? : controle, transparencia ou escrutabilidade}
        {--limpar : apaga o grupo gravado (o usuário recebe outro no próximo acesso)}';

    protected $description = 'Mostra a distribuição dos grupos do experimento, ou consulta e fixa o grupo de um usuário.';

    public function handle(Experimento $experimento): int
    {
        if (! $this->argument('usuario')) {
            return $this->distribuicao();
        }

        $usuario = User::query()
            ->where('email', $this->argument('usuario'))
            ->when(ctype_digit((string) $this->argument('usuario')), fn ($q) => $q->orWhere('id', $this->argument('usuario')))
            ->first();

        if (! $usuario) {
            $this->error('Usuário não encontrado: '.$this->argument('usuario'));

            return self::FAILURE;
        }

        if ($this->option('limpar')) {
            Feature::for($usuario)->forget(GrupoExperimento::class);
            $this->info("Grupo de {$usuario->email} apagado.");

            return self::SUCCESS;
        }

        $grupo = $this->argument('grupo');

        if ($grupo === null) {
            $atual = $this->gravado($usuario);
            $this->line("{$usuario->email}: ".($atual ?? 'sem grupo (será definido pelo link ou sorteio no próximo acesso)'));

            return self::SUCCESS;
        }

        if (! in_array($grupo, Experimento::GRUPOS, true)) {
            $this->error('Grupo inválido. Use: '.implode(', ', Experimento::GRUPOS).'.');

            return self::FAILURE;
        }

        $experimento->fixar($grupo, $usuario);
        $this->info("{$usuario->email} agora está no grupo {$grupo}.");

        if (config('experimento.forcar_grupo')) {
            $this->warn('EXPERIMENTO_FORCAR_GRUPO está definido e se sobrepõe a este grupo.');
        }

        return self::SUCCESS;
    }

    private function gravado(User $usuario): ?string
    {
        $valor = DB::table('features')
            ->where('name', 'grupo-experimento')
            ->where('scope', Feature::serializeScope($usuario))
            ->value('value');

        return $valor === null ? null : json_decode($valor);
    }

    private function distribuicao(): int
    {
        $linhas = DB::table('features')
            ->where('name', 'grupo-experimento')
            ->get(['scope', 'value'])
            ->groupBy(fn ($f) => json_decode($f->value))
            ->map(fn ($fs, $grupo) => [
                $grupo,
                $fs->filter(fn ($f) => ! str_starts_with($f->scope, 'visitante:'))->count(),
                $fs->filter(fn ($f) => str_starts_with($f->scope, 'visitante:'))->count(),
            ])
            ->values();

        $this->table(['Grupo', 'Usuários', 'Visitantes'], $linhas);

        if (config('experimento.forcar_grupo')) {
            $this->warn('EXPERIMENTO_FORCAR_GRUPO='.config('experimento.forcar_grupo').': todos veem este grupo.');
        }

        return self::SUCCESS;
    }
}
