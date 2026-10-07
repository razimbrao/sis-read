<?php

namespace App\Livewire;

use App\Models\Collaborator;
use App\Models\Data;
use App\Recommendation\RuleClassifier;
use App\Recommendation\TiposPreferidos;
use Livewire\Component;

/**
 * Minha conta / Preferências: tipos preferidos salvos na conta (docs/plano-escrutabilidade.md §15).
 * Funciona sem os painéis de transparência: é configuração básica da conta.
 */
class Preferencias extends Component
{
    /**
     * Buscas recentes consultadas para sugerir os tipos que os repositórios de fato devolvem.
     */
    private const BUSCAS_RECENTES = 30;

    public array $tipos = [];

    public bool $incluirColaboradores = false;

    public bool $salvo = false;

    public function mount(): void
    {
        $this->tipos = TiposPreferidos::daConta(auth()->user());
        $this->incluirColaboradores = (bool) auth()->user()->incluir_tipos_colaboradores;
    }

    /**
     * Tipos que podem ser marcados, com a origem de cada um. Lista fechada pelo mesmo motivo do checklist
     * da busca: um tipo que nenhum REA tem não muda nada, e texto livre traz ruído (problema #12).
     *
     * @return array<string, array<int, string>>
     */
    public function opcoes(): array
    {
        $opcoes = [];

        foreach (TiposPreferidos::daConta(auth()->user()) as $tipo) {
            $opcoes[$tipo][] = 'salvo na sua conta';
        }

        foreach (RuleClassifier::normalizarTipos(Collaborator::query()->pluck('item')->all()) as $tipo) {
            $opcoes[$tipo][] = 'sugerido por colaboradores';
        }

        foreach ($this->tiposVistosEmBuscas() as $tipo) {
            $opcoes[$tipo][] = 'aparece nos repositórios';
        }

        ksort($opcoes);

        return $opcoes;
    }

    /**
     * Tipos dos REAs que foram comparados com os preferidos nas últimas buscas (hoje, os do Aquarela).
     */
    private function tiposVistosEmBuscas(): array
    {
        $tipos = [];

        $buscas = Data::query()->whereNotNull('data')->latest('searched_at')->limit(self::BUSCAS_RECENTES)->pluck('data');

        foreach ($buscas as $json) {
            foreach (json_decode($json, true) ?? [] as $rea) {
                $criterio = $rea['explicacao']['criterios']['tipo'] ?? null;

                if (RuleClassifier::tipoComparavel($criterio) && ($criterio['valor'] ?? '') !== '') {
                    $tipos[] = $criterio['valor'];
                }
            }
        }

        return RuleClassifier::normalizarTipos($tipos);
    }

    public function salvar(): void
    {
        $this->salvo = false;
        $tipos = RuleClassifier::normalizarTipos(array_filter($this->tipos, 'is_string'));

        if (array_diff($tipos, array_keys($this->opcoes()))) {
            $this->addError('tipos', 'Escolha os tipos entre as opções oferecidas.');

            return;
        }

        if (! $tipos) {
            $this->addError('tipos', 'Marque ao menos um tipo, ou use “Remover minha preferência”.');

            return;
        }

        TiposPreferidos::salvar(auth()->user(), $tipos, $this->incluirColaboradores);
        $this->tipos = $tipos;
        $this->salvo = true;
    }

    public function remover(): void
    {
        if (TiposPreferidos::daConta(auth()->user())) {
            TiposPreferidos::salvar(auth()->user(), [], false);
        }

        $this->tipos = [];
        $this->incluirColaboradores = false;
        $this->salvo = true;
    }

    public function render()
    {
        return view('livewire.preferencias', ['opcoes' => $this->opcoes()])
            ->layout('layouts.app', ['title' => 'Minhas preferências']);
    }
}
