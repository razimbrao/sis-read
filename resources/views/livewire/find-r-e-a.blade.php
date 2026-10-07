<div class="w-full">
    @if ($this->userType === 'colaborador')
        @include('livewire.partials.colaborar')

    @elseif ($this->userType === 'feedback')
        @include('livewire.partials.sugestao')

    @else
        {{-- Busca. A chave reinicia o Alpine a cada busca nova, para o formulário voltar a ficar recolhido. --}}
        <div wire:key="busca-{{ $this->timestampSession ?? 'nova' }}" x-data="{ mostrarForm: @js(! $this->interestApiSearch) }">
            @if ($this->interestApiSearch && $this->contexto)
                <div class="bg-white border-b border-slate-200">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center gap-3">
                        <span class="text-[15px] text-slate-600">Você buscou</span>
                        <span class="px-3.5 py-2 rounded-full bg-slate-100 font-semibold text-[15px]">{{ $this->contexto['perfil'] }}</span>
                        <span class="px-3.5 py-2 rounded-full bg-slate-100 font-semibold text-[15px]">{{ ucfirst($this->contexto['interesse']) }}</span>
                        <button type="button" x-on:click="mostrarForm = !mostrarForm" :aria-expanded="mostrarForm"
                            class="ml-auto px-3.5 py-2.5 rounded-lg border border-slate-300 font-semibold text-[15px] hover:bg-slate-50">
                            <span x-text="mostrarForm ? 'Fechar' : 'Mudar busca'">Mudar busca</span>
                        </button>
                    </div>
                </div>
            @endif

            <div x-show="mostrarForm" @if ($this->interestApiSearch) x-cloak @endif>
                @include('livewire.partials.busca-formulario')
            </div>
        </div>

        @if ($this->interestApiSearch)
            <div wire:poll.keep-alive class="max-w-6xl mx-auto px-4 sm:px-6 py-7">
                @php
                    $data = \App\Models\Data::query()->where('searched_at', $this->timestampSession)->first();
                @endphp
                @if ($data)
                    @php
                        // Sem a flag de escrutabilidade, nenhum controle de correção aparece (docs/feature-flags.md).
                        $podeCorrigir = \App\Experimento\Experimento::ativa('escrutabilidade') && $this->podeCorrigir($data);
                        $repos = $this->statusRepositorios($data);
                        $respondidos = collect($repos)->reject(fn ($r) => $r['situacao'] === 'aguardando')->count();
                        $pagina = $data->data ? $this->paginate($data) : null;
                    @endphp

                    <div class="space-y-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h1 class="text-2xl font-extrabold tracking-tight">
                                @if ($pagina)
                                    {{ $pagina->total() }} {{ $pagina->total() === 1 ? 'recurso encontrado' : 'recursos encontrados' }}
                                @else
                                    REAs encontrados nos repositórios
                                @endif
                            </h1>
                            @if ($respondidos === count($repos) && $data->time)
                                <p class="text-sm text-slate-600">Busca concluída em {{ number_format(round($data->time, 1), 1, ',', '') }} s</p>
                            @endif
                        </div>

                        @error('correcao')
                            <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-[15px]" role="alert">{{ $message }}</div>
                        @enderror

                        @if ($respondidos < count($repos))
                            @include('livewire.partials.progresso-busca', ['repos' => $repos, 'respondidos' => $respondidos])
                        @endif

                        @if ($data->data && (\App\Experimento\Experimento::ativa('painel-ordenacao') || \App\Experimento\Experimento::ativa('painel-contexto')))
                            @include('livewire.partials.transparencia-paineis', [
                                'resumo' => $this->resumoOrdenacao($data),
                                'contexto' => $this->contexto,
                                'repositorios' => $repos,
                                'ocultos' => $this->ocultos($data),
                                'podeCorrigir' => $podeCorrigir,
                                'tipos' => $this->tiposPreferidos($data),
                                'avisoPreferencia' => $avisoPreferencia,
                            ])
                        @endif

                        {{-- Um cartão por REA: a parte branca é o recurso; a faixa violeta embaixo é a explicação. --}}
                        <div id="lista-reas" class="space-y-4 scroll-mt-4">
                            @explicabilidade('explicacao-rea')
                            <p class="text-sm text-slate-600">
                                <span class="font-semibold text-slate-800">Como ler os cartões:</span>
                                em branco, o recurso recomendado; na faixa violeta “Por que este REA?”, por que o sistema o colocou nessa posição.
                            </p>
                            @endexplicabilidade

                            <ol class="space-y-4">
                                @php $ocorrencias = []; @endphp
                                @foreach ($pagina ?? [] as $rea)
                                    @php
                                        // A chave mantém a explicação aberta no REA certo quando uma correção reordena a lista.
                                        $chaveLinha = isset($rea->chave)
                                            ? 'rea-'.$rea->chave.'-'.($ocorrencias[$rea->chave] = ($ocorrencias[$rea->chave] ?? 0) + 1)
                                            : 'rea-'.$this->page.'-'.$loop->iteration;
                                    @endphp
                                    @include('livewire.partials.cartao-rea', [
                                        'rea' => $rea,
                                        'posicao' => $pagina->firstItem() + $loop->index,
                                        'chaveLinha' => $chaveLinha,
                                        'podeCorrigir' => $podeCorrigir,
                                    ])
                                @endforeach
                            </ol>
                        </div>

                        @if ($pagina && $pagina->lastPage() > 1)
                            @include('livewire.partials.paginacao', ['pagina' => $pagina])
                        @endif

                        @if ($respondidos === count($repos))
                            @if (empty(json_decode($data->data ?? '[]')))
                                <div class="p-8 rounded-2xl bg-white border border-slate-200 text-center space-y-2">
                                    <p class="text-lg font-bold">@lang('Nenhum resultado encontrado.')</p>
                                    <p class="text-slate-600">Tente outro tema ou outra etapa de ensino.</p>
                                </div>
                            @else
                                @include('livewire.partials.avaliacao-busca')
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
