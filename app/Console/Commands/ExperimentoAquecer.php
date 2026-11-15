<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAquarela;
use App\Jobs\ProcessEduplay;
use App\Jobs\ProcessMecRed;
use App\Livewire\FindREA;
use App\Models\Collaborator;
use App\Models\Data;
use App\Recommendation\RuleClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Aquece o cache da classificação de meta pela IA (meta_llm) antes das sessões: roda a busca de cada
 * tema e etapa, sem participante, e apaga as linhas depois. Com vários participantes ao mesmo tempo, as
 * chamadas à IA estouravam o tempo e o MetaClassifier entrava na pausa global.
 */
class ExperimentoAquecer extends Command
{
    protected $signature = 'experimento:aquecer {--temas= : Temas separados por vírgula (padrão: Algoritmos, Computação desplugada e Reconhecimento de padrões)}';

    protected $description = 'Roda as buscas de cada tema e etapa para preencher o cache da classificação de meta pela IA.';

    private const TEMAS_PADRAO = ['Algoritmos', 'Computação desplugada', 'Reconhecimento de padrões'];

    // O cache é por REA, não por meta, e a IA só é chamada quando a busca tem meta. Qualquer meta serve.
    private const META_DE_AQUECIMENTO = 'ma';

    public function handle(): int
    {
        $etapas = FindREA::ETAPAS;
        $fora = array_diff(array_map([RuleClassifier::class, 'normalizar'], $etapas), RuleClassifier::NIVEIS_VALIDOS);

        if ($fora) {
            $this->error('Etapas da busca que o classificador não conhece: '.implode(', ', $fora));

            return self::FAILURE;
        }

        $tipos = RuleClassifier::normalizarTipos(Collaborator::query()->pluck('item')->all());
        $linhas = [];
        $ordem = 1;

        foreach ($this->temas() as $tema) {
            $termo = $this->termoDaApi($tema);

            if ($termo === null) {
                $this->warn("Tema sem termo de API conhecido, pulado: {$tema}");

                continue;
            }

            foreach ($etapas as $etapa) {
                $this->info("Aquecendo: {$tema} | {$etapa}");

                try {
                    $resumo = $this->aquecer($termo, $tema, $etapa, $tipos, $ordem++);
                } catch (Throwable $erro) {
                    $this->warn("  Falhou: {$erro->getMessage()}");

                    continue;
                }

                $this->line(sprintf(
                    '  REAs: %d | meta classificada: %d | não avaliada: %d | sem critério de meta: %d',
                    $resumo['reas'], $resumo['classificada'], $resumo['nao_avaliada'], $resumo['sem_criterio']
                ));

                $linhas[] = [$tema, $etapa, $resumo['reas'], $resumo['classificada'], $resumo['nao_avaliada'], $resumo['sem_criterio']];
            }
        }

        if ($linhas) {
            $this->newLine();
            $this->table(['Tema', 'Etapa', 'REAs', 'Meta classificada', 'Não avaliada', 'Sem critério'], $linhas);
        }

        return self::SUCCESS;
    }

    /**
     * Temas da opção --temas, ou os padrões.
     *
     * @return array<int, string>
     */
    private function temas(): array
    {
        $temas = $this->option('temas')
            ? array_map('trim', explode(',', (string) $this->option('temas')))
            : self::TEMAS_PADRAO;

        return array_values(array_filter($temas, fn ($tema) => $tema !== ''));
    }

    /**
     * Termo que a busca manda para as APIs: a mesma grafia que FindREA::findAdequateTerm escolheria.
     */
    private function termoDaApi(string $tema): ?string
    {
        $chave = RuleClassifier::normalizar($tema);

        foreach (app(FindREA::class)->opcoesInteresse() as $opcao) {
            if (RuleClassifier::normalizar($opcao) === $chave) {
                return FindREA::termoDaApi($opcao);
            }
        }

        return null;
    }

    /**
     * Roda os três jobs em sequência para uma combinação e devolve o resumo do JSON salvo. A linha da busca
     * e as métricas são apagadas no fim, mesmo se um job falhar.
     *
     * @return array{reas: int, classificada: int, nao_avaliada: int, sem_criterio: int}
     */
    private function aquecer(string $termo, string $tema, string $etapa, array $tipos, int $ordem): array
    {
        // Chave única da combinação, no padrão de RunRecommendationSimulation.
        $time = time().'_'.$ordem;

        Data::create([
            'searched_at' => $time,
            'data' => json_encode([]),
            'finished' => false,
            'time' => 0,
        ]);

        try {
            ProcessAquarela::dispatchSync($termo, $tipos, $etapa, $time, self::META_DE_AQUECIMENTO);
            ProcessMecRed::dispatchSync($termo, $tipos, $etapa, $tema, $time, self::META_DE_AQUECIMENTO);
            ProcessEduplay::dispatchSync($termo, $etapa, $time, self::META_DE_AQUECIMENTO, $tipos);

            return $this->resumir(Data::query()->where('searched_at', $time)->first());
        } finally {
            DB::table('search_metrics')->where('searched_at', $time)->delete();
            Data::query()->where('searched_at', $time)->delete();
        }
    }

    /**
     * Quantos REAs vieram e em quantos a meta foi classificada pela IA ou ficou não avaliada.
     *
     * @return array{reas: int, classificada: int, nao_avaliada: int, sem_criterio: int}
     */
    private function resumir(?Data $data): array
    {
        $reas = $data ? (json_decode($data->data, true) ?? []) : [];
        $resumo = ['reas' => count($reas), 'classificada' => 0, 'nao_avaliada' => 0, 'sem_criterio' => 0];

        foreach ($reas as $rea) {
            $status = $rea['explicacao']['criterios']['meta']['status'] ?? null;

            match (true) {
                in_array($status, ['ok', 'falhou'], true) => $resumo['classificada']++,
                $status === 'nao_avaliado' => $resumo['nao_avaliada']++,
                default => $resumo['sem_criterio']++,
            };
        }

        return $resumo;
    }
}
