<div class="flex flex-col gap-y-4 mt-8 md:mt-16 w-full justify-center items-center">
    @if($this->userType === 'usuario')
        <div class="flex flex-col items-center w-full px-4 md:px-8">
            <label for="profile" class="block mb-2 text-sm font-medium text-gray-900">@lang('Perfil')</label>
            <input 
                type="text" 
                id="profile" 
                wire:model="profile"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-96 p-2" 
                placeholder="Ensino superior"
            />
            <div class="text-red-500 text-center">@error('profile') {{ $message }} @enderror</div>
        </div>
        <div class="flex flex-col items-center w-full px-4 md:px-8">
            <label for="interest" class="block mb-2 text-sm font-medium text-gray-900">@lang('Interesse')</label>
            <input 
                type="text" 
                id="interest" 
                wire:model="interest"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-96 p-2" 
                placeholder="Pensamento computacional" 
            />
            <div class="text-red-500 text-center">@error('interest') {{ $message }} @enderror</div>
        </div>
        <div class="flex flex-wrap justify-center gap-4 mt-2">
            <button 
                wire:click="selectUserType"
                type="button" 
                class="text-white w-full sm:w-auto min-w-32 bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:border-gray-700">
                @lang('Voltar')
            </button>
            <button 
                wire:click='search'
                type="button" 
                class="text-white w-full sm:w-auto min-w-32 bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:border-gray-700">
                @lang('Buscar')
            </button>
        </div>
        @if ($this->interestApiSearch)
            <div class="flex flex-col gap-y-4 items-center justify-center w-full px-4 md:px-8 mt-8">
                <div wire:poll.keep-alive class="w-full">
                    @if (\App\Models\Data::count() > 0)
                        @php
                            $data = \App\Models\Data::query()->where('searched_at', $this->timestampSession)->first();
                        @endphp
                        @if ($data)
                            <span class="font-semibold text-lg text-gray-900 text-center block mb-4">@lang('REAs encontrados nos repositórios')</span>

                            @php
                                $podeCorrigir = $this->podeCorrigir($data);
                            @endphp
                            @error('correcao')
                                <div class="text-red-600 text-sm text-center mb-4" role="alert">{{ $message }}</div>
                            @enderror
                            @if ($data->data)
                                @include('livewire.partials.transparencia-paineis', [
                                    'resumo' => $this->resumoOrdenacao($data),
                                    'contexto' => $this->contexto,
                                    'repositorios' => $this->statusRepositorios($data),
                                    'ocultos' => $this->ocultos($data),
                                    'podeCorrigir' => $podeCorrigir,
                                    'tipos' => $this->tiposPreferidos($data),
                                ])
                            @endif
                            
                            {{-- Um cartão por REA: a parte branca é o recurso; o painel azul embaixo é a transparência. --}}
                            <div id="lista-reas" class="w-full space-y-4 scroll-mt-4">
                                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 px-1">
                                    <span class="font-medium text-gray-700">Como ler os cartões:</span>
                                    <span>em branco, o recurso recomendado;</span>
                                    <span class="inline-flex items-center gap-1">
                                        <span class="inline-block w-3 h-3 rounded-sm bg-blue-50 border border-blue-200" aria-hidden="true"></span>
                                        no painel azul, por que o sistema o colocou nessa posição.
                                    </span>
                                </p>
                                @php
                                    $pagina = $data->data ? $this->paginate($data) : null;
                                    $ocorrencias = [];
                                    $coresMeta = ['ok' => 'text-green-700', 'falhou' => 'text-red-700', 'filtro_api' => 'text-blue-700'];
                                    $textosMeta = ['ok' => 'Compatível', 'falhou' => 'Incompatível', 'filtro_api' => 'Filtrado pelo repositório'];
                                @endphp
                                @foreach ($pagina ?? [] as $rea)
                                    @php
                                        $posicao = $pagina->firstItem() + $loop->index;
                                        // A chave mantém a explicação aberta no REA certo quando uma correção reordena a lista.
                                        $chaveLinha = isset($rea->chave)
                                            ? 'rea-'.$rea->chave.'-'.($ocorrencias[$rea->chave] = ($ocorrencias[$rea->chave] ?? 0) + 1)
                                            : 'rea-'.$this->page.'-'.$loop->iteration;
                                        $explicacao = $rea->explicacao ?? null;
                                        $faixa = \App\Recommendation\ExplanationRenderer::faixa($rea->recommended ?? null, $explicacao);
                                        $criterioMeta = collect(\App\Recommendation\ExplanationRenderer::linhas($explicacao))->firstWhere('criterio', 'meta');
                                        $destaque = auth()->user() ? $rea->recommended === 'meta_both' : $rea->recommended === 'both';
                                        $links = array_values(array_filter([
                                            $rea->link ?? null,
                                            isset($rea->id) && ($rea->repositorio ?? null) === 'MECRED' ? 'https://plataformaintegrada.mec.gov.br/recurso/'.$rea->id : null,
                                        ]));
                                        $caracteristicas = [
                                            'Tipo de interatividade' => $rea->interatividade ?? null,
                                            'Nível de interatividade' => $rea->nivel_interatividade ?? null,
                                            'Estilo de aprendizagem' => $rea->estilo_aprendizagem ?? null,
                                            'Estratégia' => $rea->estrategia ?? null,
                                        ];
                                    @endphp
                                    <article
                                        x-data="{ aberto: false }"
                                        wire:key="{{ $chaveLinha }}"
                                        @class([
                                            'bg-white rounded-lg shadow-sm overflow-hidden border',
                                            'border-yellow-300 ring-1 ring-yellow-200' => $destaque,
                                            'border-gray-200' => ! $destaque,
                                        ])
                                    >
                                        <div class="p-4 md:p-5 space-y-4">
                                            <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2 mb-1 text-xs">
                                                        <span class="font-semibold text-gray-400" title="Posição na lista">#{{ $posicao }}</span>
                                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-medium">{{ $rea->repositorio }}</span>
                                                        @if (!empty($rea->type))
                                                            <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700">{{ $rea->type }}</span>
                                                        @endif
                                                    </div>
                                                    <h3 class="text-base font-semibold text-gray-900 break-words">
                                                        @if ($links)
                                                            <a href="{{ $links[0] }}" target="_blank" class="hover:text-blue-700 hover:underline">{{ $rea->title }}</a>
                                                        @else
                                                            {{ $rea->title }}
                                                        @endif
                                                    </h3>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0" x-data="{ selection: null }">
                                                    <span class="text-xs text-gray-500">@lang('Avalie')</span>
                                                    <button
                                                        type="button"
                                                        @click="selection = selection === 'accepted' ? null : 'accepted'"
                                                        :class="selection === 'accepted' ? 'text-green-600 border-green-300 bg-green-50' : 'text-gray-400 border-gray-200 hover:text-green-500'"
                                                        class="p-1 rounded-full border transition-all duration-200 focus:outline-none"
                                                        title="Aceitar"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="selection = selection === 'denied' ? null : 'denied'"
                                                        :class="selection === 'denied' ? 'text-red-600 border-red-300 bg-red-50' : 'text-gray-400 border-gray-200 hover:text-red-500'"
                                                        class="p-1 rounded-full border transition-all duration-200 focus:outline-none"
                                                        title="Negar"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <dl class="grid grid-cols-2 lg:grid-cols-5 gap-x-4 gap-y-3 text-sm">
                                                @foreach ($caracteristicas as $rotulo => $valor)
                                                    <div class="min-w-0">
                                                        <dt class="text-xs text-gray-500">{{ $rotulo }}</dt>
                                                        <dd class="text-gray-800 break-words">{{ filled($valor) ? $valor : '—' }}</dd>
                                                    </div>
                                                @endforeach
                                                <div class="min-w-0">
                                                    <dt class="text-xs text-gray-500">@lang('Meta')</dt>
                                                    <dd @class(['break-words', $coresMeta[$criterioMeta['status'] ?? ''] ?? 'text-gray-600'])>
                                                        @if ($criterioMeta)
                                                            <span title="{{ $criterioMeta['texto'] }}">{{ $criterioMeta['icone'] }} {{ $textosMeta[$criterioMeta['status']] ?? 'Não verificado' }}</span>
                                                        @else
                                                            —
                                                        @endif
                                                    </dd>
                                                </div>
                                            </dl>


                                            @foreach ($links as $link)
                                                <a href="{{ $link }}" target="_blank" class="flex items-center gap-1 text-sm text-blue-600 hover:underline min-w-0">
                                                    <span class="truncate">{{ $link }}</span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5v5M19 5l-9 9M17 14v5H5V7h5" />
                                                    </svg>
                                                </a>
                                            @endforeach
                                        </div>

                                        {{-- Transparência: separada do recurso pela cor e pela borda. --}}
                                        <div class="border-t border-blue-100 bg-blue-50">
                                            <button
                                                type="button"
                                                class="w-full flex items-center justify-between gap-3 px-4 md:px-5 py-3 text-left text-sm text-blue-800 hover:bg-blue-100 focus:outline-none"
                                                :aria-expanded="aberto"
                                                @click="aberto = !aberto; if (aberto) $wire.registrarExplicacao('abriu_explicacao', @js($rea->repositorio ?? null), @js($rea->title ?? null), @js($rea->recommended ?? null))"
                                            >
                                                <span class="flex flex-wrap items-center gap-x-2 gap-y-1 min-w-0">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <circle cx="12" cy="12" r="9" />
                                                        <path stroke-linecap="round" d="M12 11v5M12 8h.01" />
                                                    </svg>
                                                    <span class="font-medium">Por que este REA?</span>
                                                    <span class="px-2 py-0.5 rounded-full text-xs bg-white border border-blue-200 text-blue-700" title="{{ $faixa['descricao'] }}">
                                                        {{ $faixa['titulo'] }}
                                                    </span>
                                                </span>
                                                <span class="flex items-center gap-1 shrink-0 text-xs">
                                                    <span x-text="aberto ? 'Ocultar' : 'Ver explicação'">Ver explicação</span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform" :class="aberto && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                                                    </svg>
                                                </span>
                                            </button>
                                            <div x-show="aberto" style="display: none" class="px-4 md:px-5 pb-4 text-gray-700">
                                                @include('livewire.partials.explicacao-rea', ['explicacao' => $explicacao, 'faixa' => $faixa, 'rea' => $rea, 'podeCorrigir' => $podeCorrigir, 'prefixo' => $chaveLinha])
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            @if ($pagina && $pagina->lastPage() > 1)
                                @php
                                    $atual = $pagina->currentPage();
                                    $ultima = $pagina->lastPage();
                                    // Primeira, última e duas vizinhas da atual; o resto vira reticências.
                                    $numeros = collect([1, $ultima, ...range(max(1, $atual - 2), min($ultima, $atual + 2))])
                                        ->unique()->sort()->values();
                                    $irParaLista = "document.getElementById('lista-reas')?.scrollIntoView({ behavior: 'smooth' })";
                                @endphp
                                <nav class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-3 w-full" aria-label="Paginação dos REAs">
                                    <p class="text-sm text-gray-600">
                                        Mostrando <span class="font-medium text-gray-900">{{ $pagina->firstItem() }}–{{ $pagina->lastItem() }}</span>
                                        de <span class="font-medium text-gray-900">{{ $pagina->total() }}</span> REAs
                                    </p>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            wire:click="prevPage"
                                            x-on:click="{{ $irParaLista }}"
                                            @disabled($atual === 1)
                                            class="inline-flex items-center gap-1 h-9 px-3 rounded-md border border-gray-200 bg-white text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" />
                                            </svg>
                                            <span class="hidden sm:inline">Anterior</span>
                                        </button>
                                        @foreach ($numeros as $i => $numero)
                                            @if ($i > 0 && $numero - $numeros[$i - 1] > 1)
                                                <span class="w-6 text-center text-gray-400" aria-hidden="true">…</span>
                                            @endif
                                            <button
                                                type="button"
                                                wire:click="irParaPagina({{ $numero }})"
                                                x-on:click="{{ $irParaLista }}"
                                                @if ($numero === $atual) aria-current="page" @endif
                                                @class([
                                                    'h-9 min-w-9 px-2 rounded-md text-sm tabular-nums',
                                                    'bg-blue-600 text-white font-semibold' => $numero === $atual,
                                                    'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50' => $numero !== $atual,
                                                ])
                                            >{{ $numero }}</button>
                                        @endforeach
                                        <button
                                            type="button"
                                            wire:click="nextPage"
                                            x-on:click="{{ $irParaLista }}"
                                            @disabled($atual === $ultima)
                                            class="inline-flex items-center gap-1 h-9 px-3 rounded-md border border-gray-200 bg-white text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                                        >
                                            <span class="hidden sm:inline">Próxima</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
                                            </svg>
                                        </button>
                                    </div>
                                </nav>
                            @endif

                            @php
                                $repos = $this->statusRepositorios($data);
                                $respondidos = collect($repos)->reject(fn ($r) => $r['situacao'] === 'aguardando')->count();
                            @endphp
                            @if ($respondidos < count($repos))
                                <div class="flex flex-col items-center justify-center mx-auto mt-6">
                                    <svg aria-hidden="true" class="w-8 h-8 text-gray-200 animate-spin dark:text-gray-600 fill-blue-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                                    </svg>
                                    <span class="mt-2">Consultando repositórios… {{ $respondidos }} de {{ count($repos) }} responderam.</span>
                                    <span class="text-xs text-gray-500">A lista continua crescendo até o último responder.</span>
                                </div>
                            @elseif (empty(json_decode($data->data ?? "[]")))
                                <div class="mt-6 text-center">
                                    <span class="text-gray-900">@lang('Nenhum resultado encontrado.')</span>
                                </div>
                            @else
                                <div class="flex flex-col gap-y-4 mt-8 w-full">
                                    <div class="flex flex-col sm:flex-row gap-4 justify-between bg-gray-50 p-4 rounded-lg">
                                        <span class="font-semibold text-gray-700 text-sm sm:text-base">
                                            Número de REA retornados: {{ $this->paginate($data)->total() }}
                                        </span>
                                        <span class="font-semibold text-gray-700 text-sm sm:text-base">
                                            Eficiência: {{ $data->time === 0 ? 0 : round($this->paginate($data)->total() / ($data->time), 2) }}
                                        </span>
                                        <span class="text-gray-700 text-sm sm:text-base">
                                            Tempo: {{ $data->time === 0 ? 0 : round($data->time, 2) }}s
                                        </span>
                                    </div>

                                    <div class="w-full max-w-2xl mx-auto mt-4">
                                        @if ($this->feedbackSent)
                                            <div class="flex flex-col items-center justify-center p-6 md:p-10 bg-white rounded-2xl shadow-sm border border-gray-100 text-center">
                                                <div class="w-16 h-16 md:w-20 md:h-20 bg-green-50 text-green-500 rounded-full flex items-center justify-center mb-5 shadow-inner">
                                                    <svg class="w-8 h-8 md:w-10 md:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <h3 class="text-xl md:text-2xl font-bold text-gray-800 mb-2">Feedback enviado!</h3>
                                                <p class="text-gray-500 mb-4 md:mb-8 text-sm md:text-base">
                                                    Muito obrigado por dedicar um tempo para nos ajudar a melhorar a sua experiência.
                                                </p>
                                            </div>
                                        @elseif ($this->rating > 0)
                                            <div class="text-center mb-4">
                                                <span class="text-green-600 font-medium">
                                                    Obrigado! Você avaliou com {{ $rating }} {{ $rating == 1 ? 'estrela' : 'estrelas' }}.
                                                </span>
                                            </div>

                                            @if ($this->rating <= 3)
                                            <div class="mt-6 space-y-5 border-t border-gray-100 pt-5 transition-all">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">O que se destacou?</label>
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach (\App\Models\FeedbackReason::all() as $reason)
                                                            <label class="cursor-pointer">
                                                                <input type="checkbox" wire:model="selectedReasons" value="{{ $reason->id }}" class="peer sr-only">
                                                                <div class="px-3 py-2 text-xs md:text-sm rounded-full border border-gray-200 text-gray-600 bg-gray-50 peer-checked:bg-gray-800 peer-checked:text-white peer-checked:border-gray-800 transition-colors duration-200">
                                                                    {{ $reason->phrase }}
                                                                </div>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                @endif

                                                <div class="mt-4">
                                                    <label for="comment" class="block text-sm font-medium text-gray-700 mb-2">Deixe um comentário (opcional)</label>
                                                    <textarea 
                                                        id="comment" 
                                                        wire:model="comment" 
                                                        rows="3" 
                                                        class="w-full rounded-lg border-gray-200 shadow-sm focus:border-gray-800 focus:ring-gray-800 text-sm p-3 resize-none" 
                                                        placeholder="Conte mais detalhes sobre sua experiência..."
                                                    ></textarea>
                                                </div>

                                                @error('feedback_vazio')
                                                    <span class="text-red-500 text-sm font-medium block mt-2">
                                                        {{ $message }}
                                                    </span>
                                                @enderror
                                                <button 
                                                    wire:click="saveSearchFeedback" 
                                                    class="w-full mt-4 bg-gray-900 hover:bg-black text-white font-medium py-3 px-4 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900"
                                                >
                                                    Enviar Avaliação
                                                </button>
                                            </div>
                                        @else
                                            <div 
                                                x-data="{ hoverRating: 0 }" 
                                                class="flex flex-col items-center justify-center space-y-3 p-4 md:p-6 bg-white rounded-lg shadow-sm border border-gray-100"
                                            >
                                                <h3 class="text-base md:text-lg font-semibold text-gray-800 text-center">Como foi a sua experiência?</h3>

                                                <div class="flex space-x-1 sm:space-x-2">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <button
                                                            type="button"
                                                            class="focus:outline-none transition-transform duration-200 hover:scale-110"
                                                            x-on:mouseenter="hoverRating = {{ $i }}"
                                                            x-on:mouseleave="hoverRating = 0"
                                                            wire:click="setRating({{ $i }})"
                                                        >
                                                            <svg
                                                                class="w-8 h-8 md:w-10 md:h-10 transition-colors duration-200 cursor-pointer"
                                                                :class="hoverRating >= {{ $i }} || (!hoverRating && {{ $rating }} >= {{ $i }}) ? 'text-yellow-400' : 'text-gray-200'"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                viewBox="0 0 24 24"
                                                                fill="currentColor"
                                                            >
                                                                <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    @endfor
                                                </div>
                                                <span class="text-gray-400 text-xs md:text-sm text-center">Toque em uma estrela para avaliar</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endif
                </div>
            </div>
        @endif

    @elseif($this->userType === 'colaborador')
        <span class="text-gray-700 text-center max-w-lg px-4 text-sm md:text-base">@lang('A sua inserção melhorará a base de referência para a busca, que não retornará os REAs inseridos aqui, mas os usará como referência para a busca nos repositórios.')</span>
        
        <div class="flex flex-col items-center w-full px-4 md:px-8 mt-4">
            <label for="name" class="block mb-2 text-sm font-medium text-gray-900">@lang('Nome completo')</label>
            <input 
                type="text" 
                id="name" 
                wire:model="name"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-96 p-2" 
                placeholder="Nome completo"
            />
            <div class="text-red-500 text-center">@error('name') {{ $message }} @enderror</div>
        </div>
        <div class="flex flex-col items-center w-full px-4 md:px-8 mt-4">
            <label for="role" class="block mb-2 text-sm font-medium text-gray-900">@lang('Função')</label>
            <input 
                type="text" 
                id="role" 
                wire:model="role"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-96 p-2" 
                placeholder="Professor" 
            />
            <div class="text-red-500 text-center">@error('role') {{ $message }} @enderror</div>
        </div>
        <div class="flex flex-col items-center w-full px-4 md:px-8 mt-4">
            <label for="institution" class="block mb-2 text-sm font-medium text-gray-900">@lang('Instituição')</label>
            <input 
                type="text" 
                id="institution" 
                wire:model="institution"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-96 p-2" 
                placeholder="Instituição" 
            />
            <div class="text-red-500 text-center">@error('institution') {{ $message }} @enderror</div>
        </div>
        <div class="flex flex-col items-center w-full px-4 md:px-8 mt-4">
            <label for="reaTitle" class="block mb-2 text-sm font-medium text-gray-900">@lang('Título do REA')</label>
            <input 
                type="text" 
                id="reaTitle" 
                wire:model="reaTitle"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-96 p-2" 
                placeholder="Introdução ao Pensamento computacional" 
            />
            <div class="text-red-500 text-center">@error('reaTitle') {{ $message }} @enderror</div>
        </div>
        <div class="flex flex-col items-center w-full px-4 md:px-8 mt-4">
            <label for="reference" class="block mb-2 text-sm font-medium text-gray-900">@lang('Referência')</label>
            <input 
                type="text" 
                id="reference" 
                wire:model="reference"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-96 p-2" 
                placeholder="Autor" 
            />
            <div class="text-red-500 text-center">@error('reference') {{ $message }} @enderror</div>
        </div>
        <div class="flex flex-col items-center w-full px-4 md:px-8 mt-4">
            <label for="profile" class="block mb-2 text-sm font-medium text-gray-900">@lang('Perfil')</label>
            <input 
                type="text" 
                id="profile" 
                wire:model="profile"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-96 p-2" 
                placeholder="Ensino superior"
            />
            <div class="text-red-500 text-center">@error('profile') {{ $message }} @enderror</div>
        </div>
        <div class="flex flex-col items-center w-full px-4 md:px-8 mt-4">
            <label for="interest" class="block mb-2 text-sm font-medium text-gray-900">@lang('Interesse')</label>
            <input 
                type="text" 
                id="interest" 
                wire:model="interest"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-96 p-2" 
                placeholder="Pensamento computacional" 
            />
            <div class="text-red-500 text-center">@error('interest') {{ $message }} @enderror</div>
        </div>
        <div class="flex flex-col items-center w-full px-4 md:px-8 mt-4">
            <label for="item" class="block mb-2 text-sm font-medium text-gray-900">@lang('Item')</label>
            <input 
                type="text" 
                id="item" 
                wire:model="item"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-96 p-2" 
                placeholder="Trilha de aprendizagem" 
            />
            <div class="text-red-500 text-center">@error('item') {{ $message }} @enderror</div>
        </div>
        <div class="flex flex-wrap justify-center gap-4 mt-6">
            <button 
                wire:click="selectUserType"
                type="button" 
                class="text-white w-full sm:w-auto min-w-32 bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:border-gray-700">
                @lang('Voltar')
            </button>
            <button 
                wire:click='insert'
                type="button" 
                class="text-white w-full sm:w-auto min-w-32 bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:border-gray-700">
                @lang('Inserir')
            </button>
        </div>
        @if($this->showMessage)
            <span class="block mt-4 text-md font-medium text-green-600 text-center">@lang('Registro inserido com sucesso!')</span>
        @endif

    @elseif($this->userType === 'feedback')
        <div class="flex flex-col items-center w-full px-4 md:px-8">
            <label for="message" class="block mb-2 text-sm font-medium text-gray-900">@lang('Deixe seu feedback')</label>
            <textarea 
                id="message" 
                maxlength="4096"
                wire:model.live="message"
                rows="8"
                class="bg-gray-50 border border-gray-300 text-black text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full md:w-2/3 lg:w-1/2 p-3 resize-y" 
            ></textarea>
            <div class="text-red-500 mt-1">@error('message') {{ $message }} @enderror</div>
            <div class="mt-2 flex justify-end w-full md:w-2/3 lg:w-1/2 text-sm text-gray-600">
                <span>{{ $charCount }}</span> @lang('/4096')
            </div>
        </div>
        <div class="flex flex-wrap justify-center gap-4 mt-6">
            <button 
                wire:click="selectUserType"
                type="button" 
                class="text-white w-full sm:w-auto min-w-32 bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:border-gray-700">
                @lang('Voltar')
            </button>
            <button 
                wire:click='sendFeedback'
                type="button" 
                class="text-white w-full sm:w-auto min-w-32 bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:hover:bg-gray-700 dark:focus:ring-gray-700 dark:border-gray-700">
                @lang('Enviar')
            </button>
        </div>
        @if($this->showMessage)
            <span class="block mt-4 text-md font-medium text-green-600 text-center">@lang('Feedback enviado com sucesso!')</span>
        @endif

    @else
        <div class="w-full px-4 md:px-8 flex flex-col items-center">
            <span class="block mb-4 text-lg md:text-xl font-medium text-gray-900 text-center">@lang('Você é usuário ou colaborador?')</span>
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center w-full sm:w-auto">
                <button 
                    wire:click="selectUserType('colaborador')"
                    type="button" 
                    class="text-white w-full sm:w-auto min-w-36 bg-emerald-600 hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5">
                    @lang('Colaborador')
                </button>
                <button 
                    wire:click="selectUserType('usuario')"
                    type="button" 
                    class="text-white w-full sm:w-auto min-w-36 bg-emerald-600 hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5">
                    @lang('Usuário')
                </button>
            </div>
            <button 
                wire:click="selectUserType('feedback')"
                type="button" 
                class="text-white mt-4 w-full sm:w-auto min-w-36 bg-black hover:bg-zinc-700 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5">
                @lang('Deixe seu feedback')
            </button>
        </div>
        
        <div class="mt-8 w-full">
            @if(!auth()->check())
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 w-full px-4 md:px-8">
                    <a href="{{ route('login') }}" class="w-full sm:w-auto">
                        <button 
                            type="button" 
                            class="text-white w-full sm:w-auto min-w-36 bg-emerald-600 hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5">
                            @lang('Login')
                        </button>
                    </a>
                    <a href="{{ route('register') }}" class="w-full sm:w-auto">
                        <button
                            type="button"
                            class="text-white w-full sm:w-auto min-w-36 bg-emerald-600 hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5">
                            @lang('Registrar')
                        </button>
                    </a>
                </div>
            @else
                <div class="flex flex-col items-center justify-center w-full px-4 md:px-8 text-center">
                    <span class="font-semibold text-lg mb-2">
                        {{ auth()->user()->name }}
                    </span>
                    @if(!auth()->user()->questionnaire)
                        <a href="{{ route('emapre') }}">
                            <button 
                                type="button" 
                                class="text-white w-full sm:w-auto bg-emerald-600 hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5">
                                @lang('Responda o questionário')
                            </button>
                        </a>
                    @else
                        @if(auth()->user()->questionnaire->dominant === 'mpe')
                            <span class="font-semibold text-md text-gray-600 bg-gray-100 px-4 py-2 rounded-lg">
                                Meta Performance-Evitação
                            </span>
                        @elseif(auth()->user()->questionnaire->dominant === 'ma')
                            <span class="font-semibold text-md text-gray-600 bg-gray-100 px-4 py-2 rounded-lg">
                                Meta Aprender
                            </span>
                        @elseif(auth()->user()->questionnaire->dominant === 'mpa')
                            <span class="font-semibold text-md text-gray-600 bg-gray-100 px-4 py-2 rounded-lg">
                                Meta Performance-Aproximação
                            </span>
                        @else
                            <span class="font-semibold text-md text-gray-600 bg-gray-100 px-4 py-2 rounded-lg">
                                Nenhuma meta determinada
                            </span> 
                        @endif
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>