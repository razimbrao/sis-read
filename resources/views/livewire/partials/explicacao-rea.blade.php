@php
    use App\Recommendation\ExplanationRenderer;
    $cores = [
        'ok' => 'bg-green-100 text-green-700',
        'falhou' => 'bg-red-100 text-red-700',
        'nao_avaliado' => 'bg-gray-100 text-gray-600',
        'filtro_api' => 'bg-blue-100 text-blue-700',
    ];
    $linhas = ExplanationRenderer::linhas($explicacao);
    $mudancaFaixa = ExplanationRenderer::mudancaFaixa($explicacao);
    $grau = ExplanationRenderer::grau($explicacao);
    $chave = $rea->chave ?? null;
    $temCorrigivel = $chave && collect($linhas)->contains('corrigivel', true);
    $politica = ! empty(is_array($explicacao) ? ($explicacao['observacao'] ?? null) : ($explicacao->observacao ?? null));
@endphp
<div class="bg-white rounded-md border border-blue-100 p-4 space-y-3 text-sm">
    <div>
        <p class="text-xs uppercase tracking-wide text-gray-500">Posição na lista</p>
        <p class="font-medium text-gray-900">
            {{ $faixa['titulo'] }} <span class="font-normal text-gray-600">— {{ $faixa['descricao'] }}</span>
        </p>
        @if ($grau)
            <p class="mt-1 text-gray-700">{{ $grau['conta'] }}</p>
            @if ($grau['desempate'])
                <p class="text-xs text-gray-500">{{ $grau['desempate'] }}</p>
            @endif
        @endif
        @if ($mudancaFaixa)
            <p class="mt-1 text-amber-700">{{ ExplanationRenderer::MARCA_CORRECAO }} {{ $mudancaFaixa }}</p>
        @endif
    </div>

    <div>
        <p class="text-xs uppercase tracking-wide text-gray-500">Critérios conferidos</p>
        <p class="text-gray-700">{{ ExplanationRenderer::resumo($explicacao) }}</p>
        <ul class="mt-2 space-y-2">
            @foreach ($linhas as $linha)
                <li class="flex gap-2 items-start">
                    <span class="inline-flex items-center justify-center shrink-0 min-w-6 h-6 px-1 rounded-full text-xs font-bold {{ $cores[$linha['status']] ?? $cores['nao_avaliado'] }}" aria-hidden="true">
                        {{ $linha['icone'] }}@if ($linha['corrigido'])<span class="text-amber-700">{{ ExplanationRenderer::MARCA_CORRECAO }}</span>@endif
                    </span>
                    <span class="min-w-0 break-words">
                        <span>{{ $linha['texto'] }}</span>
                        @if ($chave && $linha['corrigivel'] && $podeCorrigir)
                            <span class="block mt-1">
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

    <div class="pt-3 border-t border-gray-100 space-y-1 text-xs text-gray-500">
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
        <p>
            Legenda: ✓ atende · ✗ não atende · ? não verificado · ▽ filtrado pelo repositório · {{ ExplanationRenderer::MARCA_CORRECAO }} corrigido por você
        </p>
    </div>
</div>
