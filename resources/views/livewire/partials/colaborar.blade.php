{{-- Cadastro de colaborador: o REA não aparece nos resultados, mas o tipo dele vira referência para a busca. --}}
@php
    $campo = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-base';
    $rotulo = 'block text-[15px] font-semibold mb-1.5';
    $tiposConhecidos = \App\Recommendation\RuleClassifier::normalizarTipos(\App\Models\Collaborator::query()->pluck('item')->all());
@endphp
<section class="max-w-4xl mx-auto px-4 sm:px-6 py-10 space-y-6">
    <div class="space-y-2">
        <h1 class="text-3xl font-extrabold tracking-tight">Contribuir com um REA</h1>
        <p class="text-base leading-relaxed text-slate-600 max-w-prose">@lang('A sua inserção melhorará a base de referência para a busca, que não retornará os REAs inseridos aqui, mas os usará como referência para a busca nos repositórios.')</p>
    </div>

    <form wire:submit="insert" class="space-y-5">
        <fieldset class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 grid gap-4 sm:grid-cols-2">
            <legend class="text-lg font-bold px-1">Sobre o recurso</legend>
            <div class="sm:col-span-2">
                <label for="reaTitle" class="{{ $rotulo }}">@lang('Título do REA')</label>
                <input id="reaTitle" type="text" wire:model="reaTitle" class="{{ $campo }}">
                @error('reaTitle') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="item" class="{{ $rotulo }}">Tipo de recurso</label>
                <input id="item" type="text" list="tipos-conhecidos" wire:model="item" class="{{ $campo }}" placeholder="Ex.: vídeo, jogo, trilha de aprendizagem">
                <datalist id="tipos-conhecidos">
                    @foreach ($tiposConhecidos as $tipo) <option value="{{ $tipo }}"></option> @endforeach
                </datalist>
                @error('item') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="reference" class="{{ $rotulo }}">Autor ou link (referência)</label>
                <input id="reference" type="text" wire:model="reference" class="{{ $campo }}">
                @error('reference') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="profile" class="{{ $rotulo }}">Etapa de ensino</label>
                <select id="profile" wire:model="profile" class="{{ $campo }}">
                    <option value="">Escolha…</option>
                    @foreach (\App\Livewire\FindREA::ETAPAS as $etapa) <option value="{{ $etapa }}">{{ $etapa }}</option> @endforeach
                </select>
                @error('profile') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="interest" class="{{ $rotulo }}">Tema</label>
                <input id="interest" type="text" list="temas-colaborador" wire:model="interest" class="{{ $campo }}" placeholder="Ex.: algoritmos">
                <datalist id="temas-colaborador">
                    @foreach ($this->opcoesInteresse() as $opcao) <option value="{{ $opcao }}"></option> @endforeach
                </datalist>
                @error('interest') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        <fieldset class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 grid gap-4 sm:grid-cols-3">
            <legend class="text-lg font-bold px-1">Sobre você</legend>
            <div>
                <label for="name" class="{{ $rotulo }}">@lang('Nome completo')</label>
                <input id="name" type="text" autocomplete="name" wire:model="name" class="{{ $campo }}">
                @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="role" class="{{ $rotulo }}">@lang('Função')</label>
                <input id="role" type="text" wire:model="role" class="{{ $campo }}" placeholder="Ex.: professora">
                @error('role') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="institution" class="{{ $rotulo }}">@lang('Instituição')</label>
                <input id="institution" type="text" autocomplete="organization" wire:model="institution" class="{{ $campo }}">
                @error('institution') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit" class="min-h-[52px] px-7 rounded-xl bg-emerald-700 text-white text-base font-bold hover:bg-emerald-800">Enviar contribuição</button>
            <a href="{{ url('/') }}" class="text-[15px] font-semibold text-emerald-800 hover:underline underline-offset-4">Voltar à busca</a>
            @if ($this->showMessage)
                <p class="text-[15px] font-medium text-green-800" role="status">✓ @lang('Registro inserido com sucesso!')</p>
            @endif
        </div>
    </form>
</section>
