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
            @if ($resumo['corrigidos'] > 0)
                <p class="text-amber-700">
                    {{ ExplanationRenderer::MARCA_CORRECAO }}
                    {{-- Conta faixas, não itens tocados: editar os tipos preferidos altera o critério de todos os REAs comparáveis. --}}
                    @if ($resumo['mudaram_faixa'] === 0)
                        Suas correções não mudaram a faixa de nenhum REA.
                    @else
                        {{ $resumo['mudaram_faixa'] }} {{ $resumo['mudaram_faixa'] === 1 ? 'REA mudou' : 'REAs mudaram' }} de faixa por correções suas.
                    @endif
                    <button type="button" class="text-blue-600 hover:underline" wire:click="desfazerTodas"
                        wire:confirm="Desfazer todas as suas correções nesta busca?">Desfazer todas</button>
                </p>
            @endif
            @if ($resumo['ocultos'] > 0)
                @php
                    $textosMotivos = [
                        'meta_incompativel' => 'incompatíveis com a sua meta',
                        'meta_corrigida_incompativel' => 'incompatíveis com a sua meta (corrigido por você)',
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
                @if (!empty($ocultos))
                    <details wire:ignore.self class="mt-2" x-on:toggle="if ($el.open) $wire.registrarExplicacao('abriu_ocultos')">
                        <summary class="cursor-pointer text-blue-600 hover:underline">Ver os REAs que não aparecem</summary>
                        <ul class="mt-2 space-y-2">
                            @php $ocorrenciasOcultos = []; @endphp
                            @foreach ($ocultos as $o)
                                <li>
                                    <span class="font-medium">{{ $o['titulo'] }}</span>
                                    <span class="text-gray-500">({{ $o['repositorio'] }})</span><br>
                                    <span class="text-xs">{{ $textosMotivos[$o['motivo']] ?? $o['motivo'] }} — {{ $o['resumo'] }}</span>
                                    {{-- A meta é o que tira o REA da lista; corrigi-la pode trazê-lo de volta. --}}
                                    @if ($o['chave'] && $podeCorrigir && in_array('meta', $o['corrigiveis'], true))
                                        <div class="mt-1">
                                            <span class="text-xs text-gray-600">A meta deste REA está errada?</span>
                                            @include('livewire.partials.corrigir-criterio', [
                                                'criterio' => 'meta',
                                                'chave' => $o['chave'],
                                                'prefixo' => 'oculto-'.$o['chave'].'-'.($ocorrenciasOcultos[$o['chave']] = ($ocorrenciasOcultos[$o['chave']] ?? 0) + 1),
                                                'corrigido' => in_array('meta', $o['corrigidos'], true),
                                                'repositorio' => $o['repositorio'],
                                                'titulo' => $o['titulo'],
                                                'faixa' => null,
                                            ])
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($resumo['ocultos'] > count($ocultos))
                            <p class="text-xs text-gray-500 mt-2">Mostrando {{ count($ocultos) }} de {{ $resumo['ocultos'] }}.</p>
                        @endif
                    </details>
                @endif
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
                        @if ($tipos['editados'])
                            <span class="text-amber-700">{{ ExplanationRenderer::MARCA_CORRECAO }} Definidos por você nesta busca:</span>
                            {{ $tipos['atuais'] ? implode(', ', $tipos['atuais']) : 'nenhum' }}.
                            <br><span class="text-gray-500">O sistema tinha usado: {{ $tipos['originais'] ? implode(', ', $tipos['originais']) : 'nenhum' }}.</span>
                        @else
                            @if ($contexto['tipos_busca'] ?? [])
                                De colaboradores com o mesmo interesse e perfil: {{ implode(', ', $contexto['tipos_busca']) }}.
                            @else
                                Nenhum colaborador cadastrou REAs com o mesmo interesse e perfil.
                            @endif
                            @if ($contexto['tipos_gerais'] ?? [])
                                <br>De outros colaboradores: {{ implode(', ', $contexto['tipos_gerais']) }}.
                            @endif
                        @endif

                        @if (! $tipos['opcoes'])
                            {{-- Nenhum REA comparável (ex.: Aquarela não respondeu): editar não teria efeito. --}}
                        @elseif (! $podeCorrigir)
                            <p class="text-xs text-gray-500 mt-1">Você poderá editar os tipos preferidos quando todos os repositórios responderem.</p>
                        @else
                            @php
                                $origemTipo = function (string $tipo) use ($contexto, $tipos) {
                                    return match (true) {
                                        in_array($tipo, $contexto['tipos_busca'] ?? [], true) => 'colaboradores, mesmo interesse e perfil',
                                        in_array($tipo, $contexto['tipos_gerais'] ?? [], true) => 'outros colaboradores',
                                        in_array($tipo, $tipos['originais'], true) => 'usado pelo sistema',
                                        default => 'aparece nos REAs desta busca',
                                    };
                                };
                            @endphp
                            {{-- wire:ignore: o poll não pode desmarcar o que o usuário está escolhendo. A chave muda quando a lista muda. --}}
                            <div
                                wire:ignore
                                wire:key="tipos-{{ md5(json_encode($tipos['atuais'])) }}"
                                x-data="{ editando: false, selecionados: @js($tipos['atuais']) }"
                                class="mt-2"
                            >
                                <button
                                    type="button"
                                    x-show="!editando"
                                    class="text-xs text-blue-600 hover:underline focus:outline-none"
                                    @click="editando = true; $wire.registrarExplicacao('abriu_correcao')"
                                >Editar tipos preferidos</button>
                                <div x-show="editando" style="display: none" class="mt-1 space-y-2 text-xs">
                                    <p class="text-gray-600">
                                        Marque os tipos de recurso que você prefere. Os REAs do Aquarela são reclassificados com a sua escolha;
                                        os do MEC RED e do Eduplay têm posição definida por política e não mudam.
                                    </p>
                                    <fieldset class="space-y-1">
                                        <legend class="sr-only">Tipos preferidos</legend>
                                        @foreach ($tipos['opcoes'] as $tipo)
                                            <label class="flex items-center gap-2">
                                                <input type="checkbox" value="{{ $tipo }}" x-model="selecionados" class="rounded border-gray-300">
                                                <span>
                                                    {{ $tipo }}
                                                    <span class="text-gray-500">
                                                        ({{ $origemTipo($tipo) }}; {{ $tipos['contagem'][$tipo] ?? 0 }} {{ ($tipos['contagem'][$tipo] ?? 0) === 1 ? 'REA' : 'REAs' }} desta busca)
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </fieldset>
                                    <div class="flex flex-wrap gap-3">
                                        <button
                                            type="button"
                                            class="px-2 py-0.5 rounded bg-blue-600 text-white"
                                            @click="$wire.redefinirTipos(selecionados); editando = false"
                                        >Salvar</button>
                                        <button
                                            type="button"
                                            class="text-gray-600 hover:underline"
                                            @click="editando = false; selecionados = @js($tipos['atuais'])"
                                        >Cancelar</button>
                                    </div>
                                </div>
                                @if ($tipos['editados'])
                                    <button
                                        type="button"
                                        x-show="!editando"
                                        class="ml-3 text-xs text-blue-600 hover:underline focus:outline-none"
                                        @click="$wire.redefinirTipos(@js($tipos['originais']))"
                                    >Voltar aos tipos do sistema</button>
                                @endif
                            </div>
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
