@php
    use App\Recommendation\ExplanationRenderer;

    $explicacao = $rea->explicacao ?? null;
    $faixa = ExplanationRenderer::faixa($rea->recommended ?? null, $explicacao);
    $grau = ExplanationRenderer::grau($explicacao);
    $criterioMeta = collect(ExplanationRenderer::linhas($explicacao))->firstWhere('criterio', 'meta');
    // O destaque existia antes da transparência e não fica atrás de flag (docs/feature-flags.md).
    $destaque = auth()->user() ? $rea->recommended === 'meta_both' : $rea->recommended === 'both';
    $links = array_values(array_filter([
        $rea->link ?? null,
        isset($rea->id) && ($rea->repositorio ?? null) === 'MECRED' ? 'https://plataformaintegrada.mec.gov.br/recurso/'.$rea->id : null,
    ]));
    // Só as características preenchidas: "Não especificado" em todo cartão vira ruído.
    $caracteristicas = collect([
        'Interatividade' => $rea->interatividade ?? null,
        'Nível de interatividade' => $rea->nivel_interatividade ?? null,
        'Estilo de aprendizagem' => $rea->estilo_aprendizagem ?? null,
        'Estratégia' => $rea->estrategia ?? null,
    ])->filter(fn ($v) => filled($v) && mb_strtolower(trim($v)) !== 'não especificado');
    $descricao = filled($rea->descricao ?? null) ? \Illuminate\Support\Str::limit(trim(html_entity_decode(strip_tags($rea->descricao))), 260) : null;
    $textosMeta = ['ok' => 'compatível com a sua meta', 'falhou' => 'diferente da sua meta', 'filtro_api' => 'filtrada pelo repositório'];
@endphp
<li
    x-data="{ aberto: false }"
    wire:key="{{ $chaveLinha }}"
    @class([
        'bg-white rounded-2xl overflow-hidden border',
        'border-emerald-500 ring-1 ring-emerald-500' => $destaque,
        'border-slate-200' => ! $destaque,
    ])
>
    <article class="p-5 sm:p-6 flex flex-wrap gap-5">
        <div class="flex-[999_1_480px] min-w-0 space-y-2.5">
            <div class="flex flex-wrap items-center gap-2 text-[13px] font-semibold">
                <span class="text-slate-500 font-medium" title="Posição na lista">#{{ $posicao }}</span>
                @explicabilidade('explicacao-rea')
                    @if ($grau || $faixa)
                        <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-800" title="{{ $grau['conta'] ?? $faixa['descricao'] }}">
                            @if ($grau)
                                <span class="inline-flex gap-0.5" aria-hidden="true">
                                    @for ($i = 1; $i <= $grau['maximo']; $i++)
                                        <span @class(['w-1.5 h-3 rounded-sm', 'bg-indigo-700' => $i <= $grau['total'], 'bg-indigo-200' => $i > $grau['total']])></span>
                                    @endfor
                                </span>
                            @endif
                            {{ $grau ? $grau['selo'].' · ' : '' }}{{ $faixa['titulo'] }}
                        </span>
                    @endif
                @endexplicabilidade
                <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">{{ $rea->repositorio }}</span>
                @if (! empty($rea->type))
                    <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">{{ $rea->type }}</span>
                @endif
            </div>

            <h2 class="text-lg sm:text-xl font-bold leading-snug break-words">
                @if ($links)
                    <a href="{{ $links[0] }}" target="_blank" rel="noopener" class="hover:text-emerald-800 hover:underline underline-offset-4">{{ $rea->title }}</a>
                @else
                    {{ $rea->title }}
                @endif
            </h2>

            @if ($descricao)
                <p class="text-[15px] leading-relaxed text-slate-600 max-w-prose line-clamp-2">{{ $descricao }}</p>
            @endif

            @if ($caracteristicas->isNotEmpty() || $criterioMeta)
                <p class="text-sm text-slate-600">
                    {{ $caracteristicas->map(fn ($v, $k) => $k.': '.$v)->implode(' · ') }}
                    @explicabilidade('explicacao-rea')
                        @if ($criterioMeta)
                            @if ($caracteristicas->isNotEmpty()) · @endif
                            <span title="{{ $criterioMeta['texto'] }}" @class([
                                'font-medium',
                                'text-green-800' => $criterioMeta['status'] === 'ok',
                                'text-red-800' => $criterioMeta['status'] === 'falhou',
                            ])>Meta: {{ $criterioMeta['icone'] }} {{ $textosMeta[$criterioMeta['status']] ?? 'não verificada' }}</span>
                        @endif
                    @endexplicabilidade
                </p>
            @endif
        </div>

        @if ($links)
            <div class="flex-[1_1_180px] flex flex-col justify-center gap-2">
                <a href="{{ $links[0] }}" target="_blank" rel="noopener"
                   class="inline-flex justify-center items-center gap-2 min-h-12 px-5 rounded-xl bg-emerald-700 text-white text-[15px] font-bold hover:bg-emerald-800">
                    Abrir recurso
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 5h5v5M19 5l-9 9M17 14v5H5V7h5"/></svg>
                    <span class="sr-only">(abre em nova aba)</span>
                </a>
                @if (isset($links[1]))
                    <a href="{{ $links[1] }}" target="_blank" rel="noopener" class="text-center text-sm font-semibold text-emerald-800 hover:underline underline-offset-4">Ver no MEC RED</a>
                @endif
            </div>
        @else
            {{-- Sem link (ex.: a busca do MEC RED não devolve o endereço, problema #22). --}}
            @php $semLinkId = 'sem-link-'.substr(md5(($rea->chave ?? '').($rea->title ?? '')), 0, 10); @endphp
            <div class="flex-[1_1_180px] flex flex-col justify-center gap-2">
                <span class="inline-flex" title="O repositório não informou o link deste recurso.">
                    <button type="button" disabled aria-describedby="{{ $semLinkId }}"
                            class="w-full inline-flex justify-center items-center gap-2 min-h-12 px-5 rounded-xl bg-slate-200 text-slate-500 text-[15px] font-bold cursor-not-allowed">
                        Abrir recurso
                    </button>
                </span>
                <span id="{{ $semLinkId }}" class="sr-only">Link indisponível: o repositório não informou o endereço deste recurso.</span>
            </div>
        @endif
    </article>

    {{-- Explicação: separada do recurso pela cor violeta. --}}
    @explicabilidade('explicacao-rea')
    <div class="border-t border-indigo-100 bg-indigo-50/60">
        <button
            type="button"
            class="w-full flex items-center justify-between gap-3 px-5 sm:px-6 min-h-12 py-3 text-left text-[15px] font-semibold text-indigo-800 hover:bg-indigo-100/70"
            :aria-expanded="aberto"
            @click="aberto = !aberto; if (aberto) $wire.registrarExplicacao('abriu_explicacao', @js($rea->repositorio ?? null), @js($rea->title ?? null), @js($rea->recommended ?? null))"
        >
            <span class="inline-flex items-center gap-2">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5v.4M12 17h.01"/></svg>
                Por que este REA?
            </span>
            <svg class="w-[18px] h-[18px] transition-transform" :class="aberto && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div x-show="aberto" style="display: none" class="px-5 sm:px-6 pb-5">
            @include('livewire.partials.explicacao-rea', ['explicacao' => $explicacao, 'faixa' => $faixa, 'rea' => $rea, 'podeCorrigir' => $podeCorrigir, 'prefixo' => $chaveLinha])
        </div>
    </div>
    @endexplicabilidade
</li>
