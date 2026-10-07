{{-- Formulário de busca: etapa (4 opções fixas) e tema (dropdown agrupado). --}}
@php
    $temas = $this->temasAgrupados();
    $frequentes = $this->buscasFrequentes();
    $pill = 'inline-flex items-center min-h-12 px-5 rounded-full border-2 text-base font-semibold cursor-pointer transition-colors';
@endphp
<section class="max-w-6xl mx-auto px-4 sm:px-6 {{ $this->interestApiSearch ? 'py-6' : 'pt-12 pb-8' }} flex flex-wrap gap-10 items-start">
    <div class="flex-[999_1_560px] min-w-0 space-y-7">
        @unless ($this->interestApiSearch)
            <div class="space-y-3 max-w-2xl">
                <p class="text-sm font-bold uppercase tracking-wider text-emerald-700">Pensamento Computacional</p>
                <h1 class="text-4xl font-extrabold leading-tight tracking-tight">Encontre recursos abertos para a sua turma</h1>
                <p class="text-lg leading-relaxed text-slate-600">O SisREAd consulta o Aquarela, o MEC RED e o Eduplay e coloca primeiro os recursos que combinam com a etapa de ensino que você escolher.</p>
            </div>
        @endunless

        <form wire:submit="search" class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-7 space-y-7 shadow-sm">
            <fieldset class="space-y-3">
                <legend class="text-base font-bold mb-3 flex items-center gap-2.5">
                    <span class="inline-flex w-7 h-7 rounded-full bg-emerald-700 text-white text-sm items-center justify-center" aria-hidden="true">1</span>
                    Para qual etapa de ensino?
                </legend>
                <div class="flex flex-wrap gap-2.5">
                    @foreach (\App\Livewire\FindREA::ETAPAS as $etapa)
                        <label class="{{ $pill }} border-slate-300 bg-white text-slate-900 has-[:checked]:bg-emerald-700 has-[:checked]:border-emerald-700 has-[:checked]:text-white has-[:focus-visible]:outline has-[:focus-visible]:outline-[3px] has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-emerald-700">
                            <input type="radio" name="etapa" value="{{ $etapa }}" wire:model="profile" class="sr-only">
                            {{ $etapa }}
                        </label>
                    @endforeach
                </div>
                @error('profile') <p class="text-[15px] font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset class="space-y-3">
                <legend class="text-base font-bold mb-3 flex items-center gap-2.5">
                    <span class="inline-flex w-7 h-7 rounded-full bg-emerald-700 text-white text-sm items-center justify-center" aria-hidden="true">2</span>
                    Qual tema você quer trabalhar?
                </legend>
                <label for="interest" class="sr-only">Tema</label>
                <div class="relative max-w-md">
                    <select id="interest" wire:model="interest"
                        class="w-full appearance-none min-h-12 pl-4 pr-11 py-3 text-base bg-white border border-slate-300 rounded-xl cursor-pointer focus:outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/20">
                        <option value="">Escolha um tema</option>
                        @foreach ($temas as $grupo => $opcoes)
                            <optgroup label="{{ $grupo }}">
                                @foreach ($opcoes as $valor => $rotulo)
                                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </div>
                @error('interest') <p class="text-[15px] font-medium text-red-700" role="alert">{{ $message }}</p> @enderror
            </fieldset>

            <div class="flex flex-wrap items-center gap-4 pt-5 border-t border-slate-100">
                <button type="submit" wire:loading.attr="disabled" wire:target="search"
                    class="inline-flex items-center gap-2.5 min-h-[52px] px-7 rounded-xl bg-emerald-700 text-white text-[17px] font-bold hover:bg-emerald-800 disabled:opacity-60">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    @lang('Buscar recursos')
                </button>
                <p class="text-sm text-slate-600">A busca leva de 10 a 30 segundos{{ auth()->user()?->questionnaire?->dominant ? '; com a sua meta, a primeira pode levar cerca de 1 minuto' : '' }}.</p>
            </div>
        </form>
    </div>

    @unless ($this->interestApiSearch)
        <aside class="flex-[1_1_300px] min-w-0 space-y-5">
            @if ($frequentes)
                <section class="bg-white border border-slate-200 rounded-2xl p-5 space-y-3">
                    <h2 class="text-base font-bold">Buscas frequentes</h2>
                    <p class="text-sm text-slate-600">Um clique preenche a busca.</p>
                    <div class="space-y-2">
                        @foreach ($frequentes as $b)
                            <button type="button"
                                x-on:click="$wire.profile = @js($b['perfil']); $wire.interest = @js($b['interesse'])"
                                class="w-full flex justify-between gap-3 text-left text-[15px] px-3.5 py-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100">
                                <span>{{ ucfirst($b['interesse']) }} · {{ $b['perfil'] }}</span>
                                <span class="text-sm text-slate-600">{{ $b['total'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="bg-indigo-50 border border-indigo-200 rounded-2xl p-5 space-y-3">
                <h2 class="text-base font-bold text-indigo-950">Personalize pela sua meta</h2>
                @guest
                    <p class="text-[15px] leading-relaxed text-indigo-900">Com uma conta, responda 28 frases rápidas (uns 5 minutos) e a lista passa a considerar a sua meta de aprendizagem.</p>
                    <a href="{{ route('login') }}" class="inline-flex px-4 py-2.5 rounded-xl bg-indigo-700 text-white text-[15px] font-semibold hover:bg-indigo-800">Entrar para personalizar</a>
                @else
                    @if (auth()->user()->questionnaire?->dominant)
                        <p class="text-[15px] leading-relaxed text-indigo-900">
                            Sua meta: <strong>{{ \App\Recommendation\RuleClassifier::METAS[auth()->user()->questionnaire->dominant] ?? auth()->user()->questionnaire->dominant }}</strong>.
                            As buscas já consideram essa meta.
                        </p>
                    @else
                        <p class="text-[15px] leading-relaxed text-indigo-900">Responda 28 frases rápidas (uns 5 minutos) e a lista passa a considerar a sua meta de aprendizagem.</p>
                        <a href="{{ route('emapre') }}" class="inline-flex px-4 py-2.5 rounded-xl bg-indigo-700 text-white text-[15px] font-semibold hover:bg-indigo-800">@lang('Responda o questionário')</a>
                    @endif
                    {{-- Preferências da conta: não dependem dos painéis de transparência. --}}
                    <p class="text-[15px] text-indigo-900">
                        Tipos de recurso e meta ficam em <a href="{{ route('preferencias') }}" class="font-semibold text-indigo-800 underline underline-offset-4">Minhas preferências</a>.
                    </p>
                @endguest
            </section>
        </aside>
    @endunless
</section>
