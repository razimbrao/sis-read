@php
    use App\Recommendation\ExplanationRenderer;
    // Cada status tem cor e texto: a cor nunca é a única pista.
    $status = [
        'ok' => ['classe' => 'bg-green-100 text-green-800', 'rotulo' => 'Atende'],
        'falhou' => ['classe' => 'bg-red-100 text-red-800', 'rotulo' => 'Não atende'],
        'nao_avaliado' => ['classe' => 'bg-slate-100 text-slate-700', 'rotulo' => 'Não verificado'],
        'filtro_api' => ['classe' => 'bg-sky-100 text-sky-800', 'rotulo' => 'Filtrado'],
    ];
    $linhas = ExplanationRenderer::linhas($explicacao);
    $mudancaFaixa = ExplanationRenderer::mudancaFaixa($explicacao);
    $grau = ExplanationRenderer::grau($explicacao);
    $chave = $rea->chave ?? null;
    $temCorrigivel = $chave && \App\Experimento\Experimento::ativa('escrutabilidade') && collect($linhas)->contains('corrigivel', true);
    $politica = ! empty(is_array($explicacao) ? ($explicacao['observacao'] ?? null) : ($explicacao->observacao ?? null));
@endphp
<div class="space-y-4 text-[15px]">
    <div class="bg-white rounded-xl border border-indigo-100 p-4 space-y-1.5">
        <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">Posição na lista</p>
        <p class="font-semibold text-slate-900">
            {{ $faixa['titulo'] }} <span class="font-normal text-slate-600">— {{ $faixa['descricao'] }}</span>
        </p>
        @if ($grau)
            <p class="text-slate-700">{{ $grau['conta'] }}</p>
            @if ($grau['desempate'])
                <p class="text-sm text-slate-600">{{ $grau['desempate'] }}</p>
            @endif
        @endif
        @if ($mudancaFaixa)
            <p class="mt-1 p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900">{{ ExplanationRenderer::MARCA_CORRECAO }} {{ $mudancaFaixa }}</p>
        @endif
    </div>

    <div class="space-y-2.5">
        <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">O que conferimos</p>
        <p class="text-slate-700">{{ ExplanationRenderer::resumo($explicacao) }}</p>
        <ul class="space-y-2">
            @foreach ($linhas as $linha)
                @php $s = $status[$linha['status']] ?? $status['nao_avaliado']; @endphp
                <li class="flex flex-col sm:flex-row gap-2 sm:gap-3 sm:items-start bg-white border border-slate-200 rounded-xl p-3.5">
                    <span class="self-start inline-flex items-center gap-1 shrink-0 px-2.5 py-1 rounded-full text-[13px] font-bold {{ $s['classe'] }}">
                        <span aria-hidden="true">{{ $linha['icone'] }}</span>
                        {{ $s['rotulo'] }}
                        @if ($linha['corrigido'])<span class="text-amber-800" title="corrigido por você">{{ ExplanationRenderer::MARCA_CORRECAO }}</span>@endif
                    </span>
                    <span class="min-w-0 break-words leading-relaxed">
                        <span>{{ $linha['texto'] }}</span>
                        @if ($chave && $linha['corrigivel'] && $podeCorrigir)
                            <span class="block mt-2">
                                @include('livewire.partials.corrigir-criterio', [
                                    'criterio' => $linha['criterio'],
                                    'chave' => $chave,
                                    'prefixo' => $prefixo,
                                    'corrigido' => $linha['corrigido'],
                                    'repositorio' => $rea->repositorio ?? null,
                                    'titulo' => $rea->title ?? null,
                                    'faixa' => $rea->recommended ?? null,
                                ])
                            </span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="space-y-1 text-sm text-slate-600">
        @if ($temCorrigivel && ! $podeCorrigir)
            <p>O sistema estimou o nível ou a meta deste REA. Você poderá corrigir quando todos os repositórios responderem.</p>
        @elseif ($temCorrigivel)
            <p>O sistema estimou o nível ou a meta deste REA. Se estiver errado, corrija: a lista se reorganiza com a sua informação.</p>
        @elseif ($politica)
            <p>Os critérios deste REA não podem ser corrigidos: a posição dele é definida por política do repositório.</p>
        @endif
        @foreach (ExplanationRenderer::avisos($explicacao) as $aviso)
            <p class="italic">{{ $aviso }}</p>
        @endforeach
        <p>Legenda: {{ ExplanationRenderer::MARCA_CORRECAO }} corrigido por você · ▽ filtrado pelo repositório</p>
    </div>
</div>
