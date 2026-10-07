{{-- Minha conta / Preferências (docs/plano-escrutabilidade.md §15). --}}
@php
    $questionario = auth()->user()->questionnaire;
    $metas = ['ma' => 'Aprendizagem', 'mpa' => 'Performance-aproximação', 'mpe' => 'Performance-evitação'];
@endphp
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-10 space-y-6">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Minhas preferências</h1>
        <p class="mt-1 text-[15px] text-slate-600">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
    </div>

    <form wire:submit.prevent="salvar" class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 space-y-5">
        <fieldset class="space-y-3">
            <legend class="text-lg font-bold">Tipos de recurso preferidos</legend>
            <p class="text-[15px] leading-relaxed text-slate-600">
                Valem em todas as suas buscas. Um REA com um destes tipos sobe para a faixa “Nível e tipo”
                quando o nível também bate com o seu perfil.
                @if (! auth()->user()->tipos_preferidos)
                    Hoje você não tem preferência salva: o SisREAd usa os tipos cadastrados pelos colaboradores.
                @endif
            </p>

            @if ($opcoes)
                <div class="flex flex-wrap gap-2.5 max-h-80 overflow-y-auto p-0.5">
                    @foreach ($opcoes as $tipo => $origens)
                        <label wire:key="opcao-{{ md5($tipo) }}"
                            class="inline-flex items-center gap-2.5 px-4 py-2.5 min-h-12 rounded-full border-2 border-slate-300 bg-white cursor-pointer hover:bg-slate-50 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50 has-[:focus-visible]:outline has-[:focus-visible]:outline-[3px] has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-emerald-700">
                            <input type="checkbox" value="{{ $tipo }}" wire:model="tipos" class="w-[18px] h-[18px] accent-emerald-700">
                            <span>
                                <span class="font-semibold">{{ $tipo }}</span>
                                <span class="text-sm text-slate-600">({{ implode('; ', $origens) }})</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @else
                <p class="text-[15px] text-slate-600">Ainda não há tipos para escolher. Faça uma busca primeiro.</p>
            @endif
            @error('tipos') <p class="text-sm font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
        </fieldset>

        <label class="flex items-start gap-3 p-4 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer">
            <input type="checkbox" wire:model="incluirColaboradores" class="mt-1 w-[18px] h-[18px] accent-emerald-700">
            <span>
                <span class="font-semibold">Incluir também os tipos sugeridos pelos colaboradores</span>
                <span class="block text-sm text-slate-600">Sem esta opção, os seus tipos substituem os dos colaboradores.</span>
            </span>
        </label>

        @if ($salvo)
            <p class="flex items-center gap-2 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900" role="status">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                Preferências salvas. Valem a partir da próxima busca.
            </p>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="min-h-12 px-6 rounded-xl bg-emerald-700 text-white font-bold hover:bg-emerald-800">Salvar</button>
            @if (auth()->user()->tipos_preferidos)
                <button type="button" wire:click="remover" class="min-h-12 px-2 text-[15px] font-semibold text-emerald-800 underline underline-offset-4">
                    Remover minha preferência (voltar aos tipos dos colaboradores)
                </button>
            @endif
        </div>
    </form>

    <section class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 space-y-4">
        <div>
            <h2 class="text-lg font-bold">Meta de aprendizagem</h2>
            <p class="text-[15px] text-slate-600">Vem do questionário EMAPRE. Com ela, a lista mostra primeiro os recursos compatíveis com a sua meta.</p>
        </div>
        @if ($questionario?->dominant)
            <p class="text-base">Sua meta principal: <strong>{{ $metas[$questionario->dominant] ?? $questionario->dominant }}</strong></p>
            <ul class="space-y-2">
                @foreach ($metas as $codigo => $nome)
                    @php $valor = (float) $questionario->{$codigo}; @endphp
                    <li class="grid grid-cols-[minmax(0,13rem)_1fr_2.5rem] items-center gap-3 text-[15px]">
                        <span @class(['font-bold' => $codigo === $questionario->dominant])>{{ $nome }}</span>
                        <span class="h-2.5 rounded-full bg-slate-200 overflow-hidden" aria-hidden="true">
                            <span @class(['block h-full rounded-full', 'bg-emerald-700' => $codigo === $questionario->dominant, 'bg-slate-400' => $codigo !== $questionario->dominant]) style="width: {{ max(0, min(100, $valor / 5 * 100)) }}%"></span>
                        </span>
                        <span class="text-right tabular-nums">{{ number_format($valor, 1, ',', '') }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm text-slate-600">Médias de 1 a 5. A maior define a meta.</p>
            <a href="{{ route('emapre') }}" class="inline-flex items-center min-h-12 px-5 rounded-xl border border-emerald-700 text-emerald-800 font-bold hover:bg-emerald-50">Refazer o questionário</a>
        @else
            <p class="text-[15px] text-slate-700">Você ainda não respondeu. São 28 frases rápidas (uns 5 minutos).</p>
            <a href="{{ route('emapre') }}" class="inline-flex items-center min-h-12 px-5 rounded-xl bg-emerald-700 text-white font-bold hover:bg-emerald-800">Responder o questionário</a>
        @endif
    </section>

    <p><a href="{{ url('/') }}" class="text-[15px] font-semibold text-emerald-800 hover:underline underline-offset-4">Voltar à busca</a></p>
</div>
