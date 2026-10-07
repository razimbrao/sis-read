<?php

namespace App\Console\Commands;

use App\Models\ExplanationEvent;
use App\Models\FeedbackReason;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Exporta as métricas do experimento de explicabilidade em CSV (docs/feature-flags.md §Métricas).
 *
 * Uma linha por busca (padrão) ou uma por grupo (--por-grupo). Buscas sem grupo (anteriores ao
 * experimento ou da simulação) ficam de fora.
 */
class ExperimentoExportar extends Command
{
    protected $signature = 'experimento:exportar
        {--por-grupo : uma linha por grupo, com médias e taxas}
        {--saida= : arquivo CSV de saída (padrão: imprime no terminal)}
        {--desde= : só buscas a partir desta data (AAAA-MM-DD)}
        {--separador=, : separador do CSV (use ; para abrir no Excel em português)}';

    protected $description = 'Exporta as métricas do experimento de explicabilidade por busca ou por grupo (CSV).';

    public function handle(): int
    {
        $buscas = $this->buscas();
        $linhas = $this->option('por-grupo') ? $this->porGrupo($buscas) : $buscas->all();

        if (! $linhas) {
            $this->warn('Nenhuma busca com grupo registrado.');

            return self::SUCCESS;
        }

        $saida = $this->option('saida');
        $arquivo = fopen($saida ?: 'php://output', 'w');
        $separador = (string) $this->option('separador') ?: ',';

        fputcsv($arquivo, array_keys($linhas[0]), $separador);

        foreach ($linhas as $linha) {
            fputcsv($arquivo, $linha, $separador);
        }

        fclose($arquivo);

        if ($saida) {
            $this->info(count($linhas).' linhas gravadas em '.$saida);
        }

        return self::SUCCESS;
    }

    /**
     * Uma linha por busca com grupo.
     */
    private function buscas(): Collection
    {
        $data = DB::table('data')
            ->whereNotNull('grupo')
            ->when($this->option('desde'), fn ($q, $desde) => $q->where('searched_at', '>=', $desde))
            ->orderBy('id')
            ->get(['id', 'searched_at', 'grupo', 'flags', 'participante', 'stars', 'time', DB::raw('json_array_length(data) as reas')]);

        $chaves = $data->pluck('searched_at')->all();

        $eventos = DB::table('explanation_events')->whereIn('searched_at', $chaves)
            ->select('searched_at', 'acao', DB::raw('count(*) as total'))
            ->groupBy('searched_at', 'acao')->get()->groupBy('searched_at');

        $correcoes = DB::table('corrections')->whereIn('searched_at', $chaves)
            ->select('searched_at', 'acao', DB::raw('count(*) as total'))
            ->groupBy('searched_at', 'acao')->get()->groupBy('searched_at');

        $metricas = DB::table('search_metrics')->whereIn('searched_at', $chaves)
            ->get(['searched_at', 'repository', 'items_returned', 'timeouts_errors'])->groupBy('searched_at');

        $explicacaoIds = FeedbackReason::query()->whereIn('phrase', FeedbackReason::FRASES_EXPLICACAO)->pluck('id')->all();
        $motivos = DB::table('data_reasons')->whereIn('data_id', $data->pluck('id'))
            ->get(['data_id', 'feedback_reason_id', 'feedback'])->groupBy('data_id');

        return $data->map(function ($d) use ($eventos, $correcoes, $metricas, $motivos, $explicacaoIds) {
            $ev = ($eventos[$d->searched_at] ?? collect())->pluck('total', 'acao');
            $co = ($correcoes[$d->searched_at] ?? collect())->pluck('total', 'acao');
            $me = $metricas[$d->searched_at] ?? collect();
            $mo = $motivos[$d->id] ?? collect();
            $idsMotivos = $mo->pluck('feedback_reason_id')->map(fn ($id) => (int) $id);

            $linha = [
                'busca_id' => $d->id,
                'searched_at' => $d->searched_at,
                'grupo' => $d->grupo,
                'flags' => implode('|', json_decode($d->flags ?? '[]', true) ?? []),
                'participante' => $d->participante,
                'estrelas' => $d->stars,
                'motivos' => $idsMotivos->implode('|'),
                'motivos_explicacao' => $idsMotivos->intersect($explicacaoIds)->count(),
                'comentario' => (string) $mo->pluck('feedback')->filter()->first(),
                'reas' => (int) $d->reas,
                'tempo_s' => round((float) $d->time, 2),
                'repositorios_com_falha' => $me->filter(fn ($m) => $m->timeouts_errors > 0)->count(),
            ];

            foreach (ExplanationEvent::ACOES as $acao) {
                $linha['eventos_'.$acao] = (int) ($ev[$acao] ?? 0);
            }

            $linha['correcoes'] = (int) ($co['corrigir'] ?? 0);
            $linha['correcoes_desfeitas'] = (int) ($co['desfazer'] ?? 0);

            return $linha;
        });
    }

    /**
     * Agrega as buscas por grupo: volume, avaliação, uso das explicações e correções.
     *
     * @return array<int, array<string, mixed>>
     */
    private function porGrupo(Collection $buscas): array
    {
        $feedbacks = DB::table('feedbacks')->whereNotNull('grupo')
            ->when($this->option('desde'), fn ($q, $desde) => $q->where('created_at', '>=', $desde))
            ->select('grupo', DB::raw('count(*) as total'))->groupBy('grupo')->pluck('total', 'grupo');

        return $buscas->groupBy('grupo')->map(function (Collection $bs, string $grupo) use ($feedbacks) {
            $avaliadas = $bs->filter(fn ($b) => $b['estrelas'] !== null && $b['estrelas'] > 0);
            $comMotivo = $bs->filter(fn ($b) => $b['motivos'] !== '');
            $taxa = fn (int $parte) => $bs->count() ? round($parte / $bs->count(), 4) : 0;

            $linha = [
                'grupo' => $grupo,
                'buscas' => $bs->count(),
                'participantes' => $bs->pluck('participante')->filter()->unique()->count(),
                'buscas_avaliadas' => $avaliadas->count(),
                'media_estrelas' => $avaliadas->count() ? round($avaliadas->avg('estrelas'), 3) : null,
                'buscas_com_motivo' => $comMotivo->count(),
                'buscas_com_motivo_explicacao' => $bs->filter(fn ($b) => $b['motivos_explicacao'] > 0)->count(),
                'media_reas' => round($bs->avg('reas'), 2),
                'media_tempo_s' => round($bs->avg('tempo_s'), 2),
            ];

            foreach (ExplanationEvent::ACOES as $acao) {
                $linha['taxa_buscas_'.$acao] = $taxa($bs->filter(fn ($b) => $b['eventos_'.$acao] > 0)->count());
            }

            $linha['correcoes'] = $bs->sum('correcoes');
            $linha['taxa_buscas_com_correcao'] = $taxa($bs->filter(fn ($b) => $b['correcoes'] > 0)->count());
            $linha['feedbacks_livres'] = (int) ($feedbacks[$grupo] ?? 0);

            return $linha;
        })->values()->all();
    }
}
