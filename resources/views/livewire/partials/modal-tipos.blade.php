{{-- Modal único da página para editar os tipos preferidos da busca (docs/plano-escrutabilidade.md).
     Abre pelo evento `editar-tipos` (detail opcional: `tipo`, o tipo do REA de onde o usuário veio),
     disparado pelo critério de tipo de cada cartão e pelo painel "O que usamos sobre você".
     Só é incluído com a flag de escrutabilidade, com a busca concluída e com algum REA comparável. --}}
@php
    use App\Recommendation\ExplanationRenderer;

    $origemTipo = function (string $tipo) use ($contexto, $tipos) {
        return match (true) {
            $tipos['fonte'] === 'usuario' && in_array($tipo, $contexto['tipos_conta'] ?? [], true) => 'da sua conta',
            in_array($tipo, $contexto['tipos_busca'] ?? [], true) => 'colaboradores, mesmo interesse e perfil',
            in_array($tipo, $contexto['tipos_gerais'] ?? [], true) => 'outros colaboradores',
            in_array($tipo, $tipos['originais'], true) => 'usado pelo sistema',
            default => 'aparece nos REAs desta busca',
        };
    };
    $nomes = array_combine($tipos['opcoes'], ExplanationRenderer::tipos($tipos['opcoes']));
    $podeSalvarNaConta = auth()->check() && app(\App\Experimento\Experimento::class)->salvaPreferenciaNaBusca();
@endphp
{{-- wire:ignore: o poll não pode fechar o modal nem desmarcar o que o usuário está escolhendo. A chave
     muda quando a lista muda, e aí o Livewire troca o elemento (o modal fecha com a lista nova aplicada). --}}
<div
    wire:ignore
    wire:key="modal-tipos-{{ md5(json_encode($tipos['atuais'])) }}"
    x-data="{
        aberto: false,
        selecionados: [],
        salvarNaConta: @js($podeSalvarNaConta),
        tipoRea: null,
        origem: null,
        nomes: @js($nomes),
        abrir(detalhe) {
            this.origem = document.activeElement;
            this.selecionados = @js($tipos['atuais']);
            this.tipoRea = detalhe && detalhe.tipo && this.nomes[detalhe.tipo] ? detalhe.tipo : null;
            this.aberto = true;
        },
        fechar() {
            this.aberto = false;
            this.$nextTick(() => this.origem && this.origem.isConnected && this.origem.focus());
        },
    }"
    @editar-tipos.window="abrir($event.detail)"
    @keydown.escape.window="aberto && fechar()"
>
    <div
        x-show="aberto"
        style="display: none"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/50 sm:p-4"
        @click.self="fechar()"
    >
        <div
            x-trap.noscroll="aberto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-tipos-titulo"
            class="w-full sm:max-w-xl max-h-[90vh] flex flex-col bg-white rounded-t-2xl sm:rounded-2xl shadow-xl text-[15px]"
        >
            <div class="px-5 pt-5 pb-3 border-b border-slate-100">
                <h2 id="modal-tipos-titulo" class="text-lg font-bold text-slate-900">Tipos preferidos</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Marque os tipos de recurso que você prefere. Os REAs do Aquarela e do Eduplay passam a ser comparados
                    com a sua escolha; o MEC RED não informa o tipo, então os REAs dele não mudam.
                </p>
            </div>

            <div class="px-5 py-4 space-y-3 overflow-y-auto">
                <template x-if="tipoRea && !selecionados.includes(tipoRea)">
                    <p class="p-3 rounded-lg bg-indigo-50 text-indigo-900 text-sm">
                        Este REA é do tipo <strong x-text="nomes[tipoRea]"></strong>, que não está entre os preferidos.
                        Marque-o para que os REAs desse tipo atendam ao critério de tipo.
                    </p>
                </template>
                <template x-if="tipoRea && selecionados.includes(tipoRea)">
                    <p class="p-3 rounded-lg bg-emerald-50 text-emerald-900 text-sm">
                        <strong x-text="nomes[tipoRea]"></strong> está marcado: os REAs desse tipo atendem ao critério de tipo.
                    </p>
                </template>

                <fieldset class="grid gap-1.5 sm:grid-cols-2">
                    <legend class="sr-only">Tipos preferidos</legend>
                    @foreach ($tipos['opcoes'] as $tipo)
                        <label
                            class="flex items-start gap-2 p-2 rounded-lg border hover:bg-slate-50 cursor-pointer"
                            :class="tipoRea === @js($tipo) ? 'border-indigo-400 ring-1 ring-indigo-400' : 'border-slate-100'"
                        >
                            <input type="checkbox" value="{{ $tipo }}" x-model="selecionados" class="mt-0.5 rounded border-slate-300">
                            <span>
                                <span class="font-medium text-slate-900">{{ $nomes[$tipo] }}</span>
                                <span x-show="tipoRea === @js($tipo)" class="ml-1 text-xs font-semibold text-indigo-700">tipo deste REA</span>
                                <span class="block text-sm text-slate-600">
                                    ({{ $origemTipo($tipo) }}; {{ $tipos['contagem'][$tipo] ?? 0 }} {{ ($tipos['contagem'][$tipo] ?? 0) === 1 ? 'REA' : 'REAs' }} desta busca)
                                </span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                @if ($podeSalvarNaConta)
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" x-model="salvarNaConta" class="mt-0.5 rounded border-slate-300">
                        <span>
                            Salvar também como minha preferência
                            <span class="block text-slate-600">Vale nas próximas buscas e substitui os tipos dos colaboradores. Desfazer aqui não muda a sua conta.</span>
                        </span>
                    </label>
                @endif
                <p x-show="salvarNaConta && selecionados.length === 0" class="text-sm text-red-700">
                    Para salvar na sua conta, marque ao menos um tipo.
                </p>
            </div>

            <div class="px-5 py-4 border-t border-slate-100 flex flex-wrap justify-end gap-2">
                <button type="button" class="min-h-11 px-4 rounded-lg text-slate-700 hover:bg-slate-100" @click="fechar()">Cancelar</button>
                <button
                    type="button"
                    class="min-h-11 px-4 rounded-lg bg-indigo-700 text-white font-semibold hover:bg-indigo-800 disabled:opacity-50"
                    :disabled="salvarNaConta && selecionados.length === 0"
                    @click="$wire.redefinirTipos(selecionados, salvarNaConta); fechar()"
                >Aplicar</button>
            </div>
        </div>
    </div>
</div>
