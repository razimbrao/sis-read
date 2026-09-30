@php
    use App\Recommendation\ExplanationRenderer;
    $cores = ['ok' => 'text-green-600', 'falhou' => 'text-red-600', 'nao_avaliado' => 'text-gray-500', 'filtro_api' => 'text-blue-600'];
@endphp
<div class="space-y-2 text-sm">
    <p class="font-medium text-gray-900">
        Faixa: {{ $faixa['titulo'] }} <span class="font-normal text-gray-600">({{ $faixa['descricao'] }})</span>
    </p>
    <p>{{ ExplanationRenderer::resumo($explicacao) }}</p>
    <ul class="space-y-1">
        @foreach (ExplanationRenderer::linhas($explicacao) as $linha)
            <li class="flex gap-2">
                <span class="w-4 font-bold {{ $cores[$linha['status']] ?? 'text-gray-500' }}" aria-hidden="true">{{ $linha['icone'] }}</span>
                <span>{{ $linha['texto'] }}</span>
            </li>
        @endforeach
    </ul>
    @foreach (ExplanationRenderer::avisos($explicacao) as $aviso)
        <p class="text-xs text-gray-500 italic">{{ $aviso }}</p>
    @endforeach
    <p class="text-xs text-gray-500">
        Legenda: ✓ atende · ✗ não atende · ? não verificado · ▽ filtrado pelo repositório
    </p>
</div>
