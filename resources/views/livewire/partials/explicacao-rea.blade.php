@php
    use App\Recommendation\ExplanationRenderer;
    $cores = ['ok' => 'text-green-600', 'falhou' => 'text-red-600', 'nao_avaliado' => 'text-gray-500', 'filtro_api' => 'text-blue-600'];
    $linhas = ExplanationRenderer::linhas($explicacao);
    $mudancaFaixa = ExplanationRenderer::mudancaFaixa($explicacao);
    $chave = $rea->chave ?? null;
    $temCorrigivel = $chave && collect($linhas)->contains('corrigivel', true);
    $politica = ! empty(is_array($explicacao) ? ($explicacao['observacao'] ?? null) : ($explicacao->observacao ?? null));
@endphp
<div class="space-y-2 text-sm">
    <p class="font-medium text-gray-900">
        Faixa: {{ $faixa['titulo'] }} <span class="font-normal text-gray-600">({{ $faixa['descricao'] }})</span>
    </p>
    @if ($mudancaFaixa)
        <p class="text-amber-700">{{ ExplanationRenderer::MARCA_CORRECAO }} {{ $mudancaFaixa }}</p>
    @endif
    <p>{{ ExplanationRenderer::resumo($explicacao) }}</p>
    <ul class="space-y-1">
        @foreach ($linhas as $linha)
            <li class="flex gap-2">
                <span class="w-8 shrink-0 font-bold {{ $cores[$linha['status']] ?? 'text-gray-500' }}" aria-hidden="true">
                    {{ $linha['icone'] }}@if ($linha['corrigido'])<span class="text-amber-700">{{ ExplanationRenderer::MARCA_CORRECAO }}</span>@endif
                </span>
                <span class="space-x-2">
                    <span>{{ $linha['texto'] }}</span>
                    @if ($chave && $linha['corrigivel'] && $podeCorrigir)
                        @include('livewire.partials.corrigir-criterio', [
                            'criterio' => $linha['criterio'],
                            'chave' => $chave,
                            'prefixo' => $prefixo,
                            'corrigido' => $linha['corrigido'],
                            'repositorio' => $rea->repositorio ?? null,
                            'titulo' => $rea->title ?? null,
                            'faixa' => $rea->recommended ?? null,
                        ])
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
    @if ($temCorrigivel && ! $podeCorrigir)
        <p class="text-xs text-gray-500">O sistema estimou o nível ou a meta deste REA. Você poderá corrigir quando todos os repositórios responderem.</p>
    @elseif ($temCorrigivel)
        <p class="text-xs text-gray-500">O sistema estimou o nível ou a meta deste REA. Se estiver errado, corrija: a lista se reorganiza com a sua informação.</p>
    @elseif ($politica)
        <p class="text-xs text-gray-500">Os critérios deste REA não podem ser corrigidos: a posição dele é definida por política do repositório.</p>
    @endif
    @foreach (ExplanationRenderer::avisos($explicacao) as $aviso)
        <p class="text-xs text-gray-500 italic">{{ $aviso }}</p>
    @endforeach
    <p class="text-xs text-gray-500">
        Legenda: ✓ atende · ✗ não atende · ? não verificado · ▽ filtrado pelo repositório · {{ ExplanationRenderer::MARCA_CORRECAO }} corrigido por você
    </p>
</div>
