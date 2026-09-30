@php
    use App\Recommendation\ExplanationRenderer;
    use App\Recommendation\RuleClassifier;
@endphp
<div class="grid gap-4 md:grid-cols-2 mb-4 text-sm text-gray-700">
    <details wire:ignore.self class="bg-gray-50 rounded-lg p-4" x-on:toggle="if ($el.open) $wire.registrarExplicacao('abriu_ordenacao')">
        <summary class="font-semibold text-gray-900 cursor-pointer">Como ordenamos estes resultados</summary>
        <div class="mt-3 space-y-2">
            <p>
                @if ($resumo['com_meta'])
                    Como você respondeu o questionário de metas, mostramos só REAs compatíveis com a sua meta, nesta ordem:
                @else
                    Os REAs são agrupados em faixas e mostrados nesta ordem:
                @endif
            </p>
            <ol class="list-decimal ml-5 space-y-1">
                @foreach ($resumo['ordem'] as $rotulo)
                    @php $faixa = ExplanationRenderer::faixa($rotulo); @endphp
                    <li><strong>{{ $faixa['titulo'] }}</strong>: {{ $faixa['descricao'] }} ({{ $resumo['faixas'][$rotulo] }} {{ $resumo['faixas'][$rotulo] === 1 ? 'REA' : 'REAs' }})</li>
                @endforeach
            </ol>
            @if ($resumo['ocultos'] > 0)
                @php
                    $textosMotivos = [
                        'meta_incompativel' => 'incompatíveis com a sua meta',
                        'meta_nao_avaliada' => 'sem classificação de meta (a IA não conseguiu classificar)',
                        'sem_meta_usuario' => 'que dependem de uma meta de aprendizagem',
                        'outros' => 'sem faixa definida',
                    ];
                @endphp
                <p>{{ $resumo['ocultos'] }} {{ $resumo['ocultos'] === 1 ? 'REA encontrado não é exibido' : 'REAs encontrados não são exibidos' }}:</p>
                <ul class="list-disc ml-5">
                    @foreach ($resumo['motivos_ocultos'] as $motivo => $quantidade)
                        @if ($quantidade > 0)
                            <li>{{ $quantidade }} {{ $textosMotivos[$motivo] }}</li>
                        @endif
                    @endforeach
                </ul>
            @endif
            <div>
                <p class="font-medium text-gray-900">Repositórios consultados</p>
                <ul class="ml-5 list-disc">
                    @foreach ($repositorios as $nome => $r)
                        <li>
                            {{ $nome }}:
                            @switch($r['situacao'])
                                @case('ok') {{ $r['itens'] }} {{ $r['itens'] === 1 ? 'item retornado' : 'itens retornados' }} @break
                                @case('parcial') {{ $r['itens'] }} itens; algumas páginas não responderam @break
                                @case('falhou') não respondeu (tempo esgotado ou erro); nenhum item deste repositório @break
                                @default ainda consultando…
                            @endswitch
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="text-xs text-gray-500">
                Dentro de cada faixa, a ordem é a de chegada dos repositórios. Exceções por política: os itens do MEC RED
                ficam na faixa mais alta (o SisREAd pede ao repositório itens do seu nível, mas não consegue conferir), e os do Eduplay
                são posicionados pela sua meta.
            </p>
        </div>
    </details>

    <details wire:ignore.self class="bg-gray-50 rounded-lg p-4" x-on:toggle="if ($el.open) $wire.registrarExplicacao('abriu_contexto')">
        <summary class="font-semibold text-gray-900 cursor-pointer">O que usamos sobre você</summary>
        @if ($contexto)
            <dl class="mt-3 space-y-2">
                <div>
                    <dt class="font-medium text-gray-900">Perfil (nível educacional)</dt>
                    <dd>{{ $contexto['perfil'] }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-900">Interesse</dt>
                    <dd>
                        {{ $contexto['interesse'] }}
                        @if (RuleClassifier::normalizar($contexto['interesse']) !== RuleClassifier::normalizar($contexto['termo_api']))
                            → buscado nos repositórios como “{{ $contexto['termo_api'] }}”
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-900">Tipos preferidos</dt>
                    <dd>
                        @if ($contexto['tipos_busca'])
                            De colaboradores com o mesmo interesse e perfil: {{ implode(', ', $contexto['tipos_busca']) }}.
                        @else
                            Nenhum colaborador cadastrou REAs com o mesmo interesse e perfil.
                        @endif
                        @if ($contexto['tipos_gerais'])
                            <br>De outros colaboradores: {{ implode(', ', $contexto['tipos_gerais']) }}.
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-900">Meta de aprendizagem (EMAPRE)</dt>
                    <dd>
                        @if ($contexto['meta'])
                            Dominante: {{ RuleClassifier::METAS[$contexto['meta']['dominante']] ?? $contexto['meta']['dominante'] }}.
                            Médias (1 a 5): Aprendizagem {{ $contexto['meta']['ma'] }} ·
                            Performance-aproximação {{ $contexto['meta']['mpa'] }} ·
                            Performance-evitação {{ $contexto['meta']['mpe'] }}.
                        @else
                            Não informada. Responda o questionário de metas (com login) para ordenar pela sua meta.
                        @endif
                    </dd>
                </div>
            </dl>
        @else
            <p class="mt-3">Faça uma busca para ver os dados usados.</p>
        @endif
    </details>
</div>
