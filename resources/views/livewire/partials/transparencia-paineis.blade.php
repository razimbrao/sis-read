@php
    use App\Recommendation\ExplanationRenderer;
    use App\Recommendation\RuleClassifier;

    $totalExibidos = array_sum(array_map(fn ($r) => $resumo['faixas'][$r] ?? 0, $resumo['ordem']));
    $situacoes = [
        'ok' => ['cor' => 'bg-green-500', 'texto' => 'text-gray-700'],
        'parcial' => ['cor' => 'bg-amber-500', 'texto' => 'text-amber-800'],
        'falhou' => ['cor' => 'bg-red-500', 'texto' => 'text-red-700'],
    ];
@endphp
<div class="grid gap-4 md:grid-cols-2 items-start mb-6 text-sm text-gray-700">
    {{-- Painel 1: como a lista foi montada. --}}
    @explicabilidade('painel-ordenacao')
    <details wire:ignore.self class="group bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" x-on:toggle="if ($el.open) $wire.registrarExplicacao('abriu_ordenacao')">
        <summary class="flex items-center gap-3 p-4 cursor-pointer list-none [&::-webkit-details-marker]:hidden hover:bg-gray-50">
            <span class="flex items-center justify-center w-9 h-9 rounded-full bg-blue-50 text-blue-600 shrink-0" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h6" />
                </svg>
            </span>
            <span class="flex-1 min-w-0">
                <span class="block font-semibold text-gray-900">Como ordenamos estes resultados</span>
                <span class="block text-xs text-gray-500">
                    {{ $totalExibidos }} {{ $totalExibidos === 1 ? 'REA exibido' : 'REAs exibidos' }}@if ($resumo['ocultos'] > 0) · {{ $resumo['ocultos'] }} {{ $resumo['ocultos'] === 1 ? 'oculto' : 'ocultos' }}@endif
                </span>
            </span>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
            </svg>
        </summary>

        <div class="px-4 pb-4 space-y-5 border-t border-gray-100 pt-4">
            <section class="space-y-3">
                <p>
                    Cada REA recebe um <strong>grau de recomendação</strong>: a soma dos pontos dos critérios que foram
                    conferidos e atendidos ({{ $resumo['com_meta'] ? 'meta +4, nível +2, tipo +1, de 0 a 7' : 'nível +2, tipo +1, de 0 a 3' }}).
                    Critério que não pôde ser conferido vale 0, e a regra é a mesma para todos os repositórios.
                    A lista vai do maior grau para o menor, nestas faixas:
                </p>
                <ol class="space-y-2">
                    @foreach ($resumo['ordem'] as $rotulo)
                        @php
                            $faixa = ExplanationRenderer::faixa($rotulo, null, $resumo['com_meta']);
                            $quantidade = $resumo['faixas'][$rotulo];
                        @endphp
                        <li class="flex items-start gap-3 p-3 rounded-lg bg-gray-50">
                            <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-semibold shrink-0">{{ $loop->iteration }}</span>
                            <span class="flex-1 min-w-0">
                                <span class="block font-medium text-gray-900">{{ $faixa['titulo'] }} <span class="font-normal text-gray-500">· grau {{ $faixa['graus'] }}</span></span>
                                <span class="block text-xs text-gray-600">{{ $faixa['descricao'] }}</span>
                            </span>
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-medium shrink-0',
                                'bg-blue-100 text-blue-700' => $quantidade > 0,
                                'bg-gray-200 text-gray-500' => $quantidade === 0,
                            ])>{{ $quantidade }} {{ $quantidade === 1 ? 'REA' : 'REAs' }}</span>
                        </li>
                    @endforeach
                </ol>
                @if ($resumo['com_meta'])
                    @php
                        $incompativeis = $resumo['meta_incompativel'] + $resumo['meta_corrigida_incompativel'];
                    @endphp
                    <div class="text-xs text-gray-600 space-y-1">
                        <p>
                            Abaixo dos compatíveis com a sua meta vêm primeiro os REAs cuja meta não pôde ser conferida e, no fim,
                            os de meta diferente da sua. Em cada grupo, a ordem é a mesma: do maior grau para o menor.
                            A meta não tira nenhum REA da lista, porque a classificação por IA pode errar.
                        </p>
                        @if ($resumo['meta_nao_conferida'] > 0)
                            <p>{{ $resumo['meta_nao_conferida'] }} {{ $resumo['meta_nao_conferida'] === 1 ? 'REA está' : 'REAs estão' }} com a meta não conferida.</p>
                        @endif
                        @if ($incompativeis > 0)
                            <p>
                                {{ $incompativeis }} {{ $incompativeis === 1 ? 'REA está' : 'REAs estão' }} com a meta diferente da sua{{ $resumo['meta_corrigida_incompativel'] > 0 ? ' ('.$resumo['meta_corrigida_incompativel'].' por correção sua)' : '' }}.
                            </p>
                        @endif
                    </div>
                @endif
            </section>

            @if ($resumo['corrigidos'] > 0 && $podeCorrigir)
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800">
                    <span>
                        {{ ExplanationRenderer::MARCA_CORRECAO }}
                        {{-- Conta faixas, não itens tocados: editar os tipos preferidos altera o critério de todos os REAs comparáveis. --}}
                        @if ($resumo['mudaram_faixa'] === 0)
                            Suas correções não mudaram a faixa de nenhum REA.
                        @else
                            {{ $resumo['mudaram_faixa'] }} {{ $resumo['mudaram_faixa'] === 1 ? 'REA mudou' : 'REAs mudaram' }} de faixa por correções suas.
                        @endif
                    </span>
                    <button type="button" class="font-medium text-blue-700 hover:underline" wire:click="desfazerTodas"
                        wire:confirm="Desfazer todas as suas correções nesta busca?">Desfazer todas</button>
                </div>
            @endif

            @if ($resumo['ocultos'] > 0)
                @php
                    $textosMotivos = [
                        'sem_meta_usuario' => 'que dependem de uma meta de aprendizagem',
                        'outros' => 'sem faixa definida',
                    ];
                @endphp
                <section class="rounded-lg border border-gray-200 overflow-hidden">
                    <div class="p-3 bg-gray-50">
                        <p class="flex items-center gap-2 font-medium text-gray-900">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.1A9.8 9.8 0 0112 5c5 0 9 5 9 7a11 11 0 01-2.2 3.2M6.6 6.6C4.4 8 3 10.4 3 12c0 2 4 7 9 7a9.6 9.6 0 004.4-1.1" />
                            </svg>
                            {{ $resumo['ocultos'] }} {{ $resumo['ocultos'] === 1 ? 'REA encontrado não é exibido' : 'REAs encontrados não são exibidos' }}:
                        </p>
                        <ul class="mt-1 ml-6 space-y-0.5 text-gray-600">
                            @foreach ($resumo['motivos_ocultos'] as $motivo => $quantidade)
                                @if ($quantidade > 0)
                                    <li>{{ $quantidade }} {{ $textosMotivos[$motivo] }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                    @if (!empty($ocultos) && \App\Experimento\Experimento::ativa('reas-ocultos'))
                        <details wire:ignore.self class="group/ocultos border-t border-gray-200" x-on:toggle="if ($el.open) $wire.registrarExplicacao('abriu_ocultos')">
                            <summary class="flex items-center justify-between gap-2 px-3 py-2 cursor-pointer list-none [&::-webkit-details-marker]:hidden text-blue-700 font-medium hover:bg-blue-50">
                                <span>Ver os REAs que não aparecem</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 transition-transform group-open/ocultos:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                                </svg>
                            </summary>
                            <ul class="p-3 space-y-2 max-h-[28rem] overflow-y-auto bg-white">
                                @php $ocorrenciasOcultos = []; @endphp
                                @foreach ($ocultos as $o)
                                    <li class="p-3 rounded-lg border border-gray-200">
                                        <div class="flex flex-wrap items-center gap-2 mb-1 text-xs">
                                            <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-medium">{{ $o['repositorio'] }}</span>
                                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $textosMotivos[$o['motivo']] ?? $o['motivo'] }}</span>
                                        </div>
                                        <p class="font-medium text-gray-900 break-words">{{ $o['titulo'] }}</p>
                                        <p class="mt-1 text-xs text-gray-600">{{ $o['resumo'] }}</p>
                                        {{-- A meta é o que tira o REA da lista; corrigi-la pode trazê-lo de volta. --}}
                                        @if ($o['chave'] && $podeCorrigir && in_array('meta', $o['corrigiveis'], true))
                                            <div class="mt-2 pt-2 border-t border-gray-100 flex flex-wrap items-center gap-2">
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
                                <p class="px-3 pb-3 text-xs text-gray-500">Mostrando {{ count($ocultos) }} de {{ $resumo['ocultos'] }}.</p>
                            @endif
                        </details>
                    @endif
                </section>
            @endif

            <section>
                <p class="text-xs uppercase tracking-wide text-gray-500 mb-2">Repositórios consultados</p>
                <ul class="space-y-1.5">
                    @foreach ($repositorios as $nome => $r)
                        @php $s = $situacoes[$r['situacao']] ?? ['cor' => 'bg-gray-300 animate-pulse', 'texto' => 'text-gray-500']; @endphp
                        <li class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full shrink-0 {{ $s['cor'] }}" aria-hidden="true"></span>
                            <span class="font-medium text-gray-900">{{ $nome }}</span>
                            <span class="{{ $s['texto'] }}">
                                @switch($r['situacao'])
                                    @case('ok') {{ $r['itens'] }} {{ $r['itens'] === 1 ? 'item retornado' : 'itens retornados' }} @break
                                    @case('parcial') {{ $r['itens'] }} itens; algumas páginas não responderam @break
                                    @case('falhou') não respondeu (tempo esgotado ou erro); nenhum item deste repositório @break
                                    @default ainda consultando…
                                @endswitch
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <p class="text-xs text-gray-500 pt-3 border-t border-gray-100">
                Entre REAs de mesmo grau, vem primeiro o que está mais acima no próprio repositório (o 1º resultado de cada
                repositório, depois o 2º, e assim por diante), o que intercala os repositórios. Persistindo o empate, vale a ordem
                alfabética do repositório. Nenhum repositório tem posição reservada, e a ordem não depende de qual respondeu primeiro.
            </p>
        </div>
    </details>
    @endexplicabilidade

    {{-- Painel 2: os dados do usuário que entraram na recomendação. --}}
    @explicabilidade('painel-contexto')
    <details wire:ignore.self class="group bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" x-on:toggle="if ($el.open) $wire.registrarExplicacao('abriu_contexto')">
        <summary class="flex items-center gap-3 p-4 cursor-pointer list-none [&::-webkit-details-marker]:hidden hover:bg-gray-50">
            <span class="flex items-center justify-center w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 shrink-0" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="8" r="4" />
                    <path stroke-linecap="round" d="M4 20c0-4 4-6 8-6s8 2 8 6" />
                </svg>
            </span>
            <span class="flex-1 min-w-0">
                <span class="block font-semibold text-gray-900">O que usamos sobre você</span>
                <span class="block text-xs text-gray-500 truncate">
                    @if ($contexto)
                        {{ $contexto['perfil'] }} · {{ $contexto['interesse'] }}@if ($contexto['meta']) · meta {{ mb_strtolower(RuleClassifier::METAS[$contexto['meta']['dominante']] ?? $contexto['meta']['dominante']) }}@endif
                    @else
                        Perfil, interesse, tipos e meta
                    @endif
                </span>
            </span>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
            </svg>
        </summary>

        <div class="px-4 pb-4 border-t border-gray-100 pt-4">
            @if ($contexto)
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="p-3 rounded-lg bg-gray-50">
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Perfil (nível educacional)</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $contexto['perfil'] }}</dd>
                    </div>
                    <div class="p-3 rounded-lg bg-gray-50">
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Interesse</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">
                            {{ $contexto['interesse'] }}
                            @if (RuleClassifier::normalizar($contexto['interesse']) !== RuleClassifier::normalizar($contexto['termo_api']))
                                <span class="block text-xs font-normal text-gray-600">→ buscado nos repositórios como “{{ $contexto['termo_api'] }}”</span>
                            @endif
                        </dd>
                    </div>

                    <div class="sm:col-span-2 p-3 rounded-lg bg-gray-50">
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Tipos preferidos</dt>
                        <dd class="mt-0.5 text-gray-800">
                            @if ($tipos['editados'])
                                <span class="text-amber-700">{{ ExplanationRenderer::MARCA_CORRECAO }} Definidos por você nesta busca:</span>
                                {{ $tipos['atuais'] ? implode(', ', $tipos['atuais']) : 'nenhum' }}.
                                <span class="block text-xs text-gray-500">O sistema tinha usado: {{ $tipos['originais'] ? implode(', ', $tipos['originais']) : 'nenhum' }}.</span>
                            @else
                                @if ($contexto['tipos_busca'] ?? [])
                                    De colaboradores com o mesmo interesse e perfil: {{ implode(', ', $contexto['tipos_busca']) }}.
                                @else
                                    Nenhum colaborador cadastrou REAs com o mesmo interesse e perfil.
                                @endif
                                @if ($contexto['tipos_gerais'] ?? [])
                                    <span class="block">De outros colaboradores: {{ implode(', ', $contexto['tipos_gerais']) }}.</span>
                                @endif
                            @endif

                            @if (! $tipos['opcoes'] || ! \App\Experimento\Experimento::ativa('escrutabilidade'))
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
                                    <div x-show="!editando" class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="px-3 py-1 rounded-md border border-blue-200 bg-white text-xs font-medium text-blue-700 hover:bg-blue-50 focus:outline-none"
                                            @click="editando = true; $wire.registrarExplicacao('abriu_correcao')"
                                        >Editar tipos preferidos</button>
                                        @if ($tipos['editados'])
                                            <button
                                                type="button"
                                                class="px-3 py-1 rounded-md text-xs text-blue-700 hover:underline focus:outline-none"
                                                @click="$wire.redefinirTipos(@js($tipos['originais']))"
                                            >Voltar aos tipos do sistema</button>
                                        @endif
                                    </div>
                                    <div x-show="editando" style="display: none" class="mt-1 space-y-3 p-3 rounded-lg bg-white border border-gray-200 text-xs">
                                        <p class="text-gray-600">
                                            Marque os tipos de recurso que você prefere. Os REAs do Aquarela são reclassificados com a sua escolha;
                                            os do MEC RED e do Eduplay têm posição definida por política e não mudam.
                                        </p>
                                        <fieldset class="grid gap-1.5 sm:grid-cols-2">
                                            <legend class="sr-only">Tipos preferidos</legend>
                                            @foreach ($tipos['opcoes'] as $tipo)
                                                <label class="flex items-start gap-2 p-2 rounded-md border border-gray-100 hover:bg-gray-50 cursor-pointer">
                                                    <input type="checkbox" value="{{ $tipo }}" x-model="selecionados" class="mt-0.5 rounded border-gray-300">
                                                    <span>
                                                        <span class="font-medium text-gray-900">{{ $tipo }}</span>
                                                        <span class="block text-gray-500">
                                                            ({{ $origemTipo($tipo) }}; {{ $tipos['contagem'][$tipo] ?? 0 }} {{ ($tipos['contagem'][$tipo] ?? 0) === 1 ? 'REA' : 'REAs' }} desta busca)
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </fieldset>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="px-3 py-1 rounded-md bg-blue-600 text-white font-medium hover:bg-blue-700"
                                                @click="$wire.redefinirTipos(selecionados); editando = false"
                                            >Salvar</button>
                                            <button
                                                type="button"
                                                class="px-3 py-1 rounded-md text-gray-600 hover:bg-gray-100"
                                                @click="editando = false; selecionados = @js($tipos['atuais'])"
                                            >Cancelar</button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </dd>
                    </div>

                    <div class="sm:col-span-2 p-3 rounded-lg bg-gray-50">
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Meta de aprendizagem (EMAPRE)</dt>
                        <dd class="mt-0.5 text-gray-800">
                            @if ($contexto['meta'])
                                <p>
                                    Dominante:
                                    <span class="font-medium text-gray-900">{{ RuleClassifier::METAS[$contexto['meta']['dominante']] ?? $contexto['meta']['dominante'] }}</span>
                                </p>
                                <p class="mt-2 text-xs text-gray-500">Médias (1 a 5)</p>
                                <ul class="mt-1 space-y-1.5">
                                    @foreach (['ma' => 'Aprendizagem', 'mpa' => 'Performance-aproximação', 'mpe' => 'Performance-evitação'] as $codigo => $nome)
                                        @php $valor = (float) $contexto['meta'][$codigo]; @endphp
                                        <li class="grid grid-cols-[minmax(0,11rem)_1fr_2.5rem] items-center gap-2 text-xs">
                                            <span @class(['truncate', 'font-semibold text-gray-900' => $codigo === $contexto['meta']['dominante']])>{{ $nome }}</span>
                                            <span class="h-2 rounded-full bg-gray-200 overflow-hidden" aria-hidden="true">
                                                <span @class([
                                                    'block h-full rounded-full',
                                                    'bg-emerald-500' => $codigo === $contexto['meta']['dominante'],
                                                    'bg-gray-400' => $codigo !== $contexto['meta']['dominante'],
                                                ]) style="width: {{ max(0, min(100, $valor / 5 * 100)) }}%"></span>
                                            </span>
                                            <span class="text-right tabular-nums">{{ $contexto['meta'][$codigo] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                Não informada. Responda o questionário de metas (com login) para ordenar pela sua meta.
                            @endif
                        </dd>
                    </div>
                </dl>
            @else
                <p>Faça uma busca para ver os dados usados.</p>
            @endif
        </div>
    </details>
    @endexplicabilidade
</div>
