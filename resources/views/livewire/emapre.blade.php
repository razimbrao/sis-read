@php
    $opcoes = [1 => 'Discordo Completamente', 2 => 'Discordo', 3 => 'Não sei', 4 => 'Concordo', 5 => 'Concordo Completamente'];
    $total = collect($questions)->flatten()->count();
@endphp
<div
    x-data="{ respondidas: 0, contar() { this.respondidas = this.$root.querySelectorAll('input[type=radio]:checked').length } }"
    x-init="contar()"
    x-on:change="contar()"
>
    <div class="sticky top-0 z-10 bg-white/95 backdrop-blur border-b border-slate-200">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-3 space-y-2">
            <div class="flex justify-between text-sm text-slate-600">
                <span class="font-semibold text-slate-900">Questionário de metas</span>
                <span><span x-text="respondidas">0</span> de {{ $total }} respondidas</span>
            </div>
            <div class="h-2 rounded-full bg-slate-200 overflow-hidden" role="progressbar" aria-label="Frases respondidas" aria-valuemin="0" aria-valuemax="{{ $total }}" :aria-valuenow="respondidas">
                <div class="h-full bg-emerald-700 transition-all" :style="`width: ${respondidas / {{ $total }} * 100}%`"></div>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8 space-y-6">
        <div class="space-y-2">
            <h1 class="text-3xl font-extrabold tracking-tight">Questionário EMAPRE-U</h1>
            <p class="text-base leading-relaxed text-slate-600">Diga o quanto você concorda com cada frase pensando em como você estuda. Não há resposta certa. O resultado ajuda o SisREAd a ordenar os recursos pela sua meta de aprendizagem.</p>
        </div>

        @if (session()->has('success'))
            <div class="p-4 rounded-xl bg-green-50 border border-green-200 text-green-900" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-900" role="alert">
                Faltam respostas. As frases sem resposta estão marcadas em vermelho.
            </div>
        @endif

        <form wire:submit.prevent="submit" class="space-y-8">
            @foreach ($questions as $group => $items)
                <section class="space-y-4">
                    <h2 class="text-xl font-bold">{{ $group }}</h2>

                    @foreach ($items as $key => $text)
                        @php $erro = $errors->has('responses.'.$key); @endphp
                        <fieldset @class([
                            'bg-white rounded-2xl p-5 space-y-3',
                            'border-2 border-red-600' => $erro,
                            'border border-slate-200' => ! $erro,
                        ]) @if ($erro) aria-describedby="erro-{{ $key }}" @endif>
                            <legend class="sr-only">{{ $key }}. {{ $text }}</legend>
                            <p class="text-[17px] font-semibold leading-relaxed" aria-hidden="true">{{ $key }}. {{ $text }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-5 gap-2">
                                @foreach ($opcoes as $valor => $rotulo)
                                    <label class="flex sm:flex-col items-center gap-2.5 sm:gap-1.5 p-3 min-h-12 rounded-xl border-2 border-slate-300 text-sm sm:text-center cursor-pointer hover:bg-slate-50 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50 has-[:checked]:font-bold has-[:focus-visible]:outline has-[:focus-visible]:outline-[3px] has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-emerald-700">
                                        <input type="radio" name="q{{ $key }}" wire:model="responses.{{ $key }}" value="{{ $valor }}" class="w-5 h-5 accent-emerald-700">
                                        {{ $rotulo }}
                                    </label>
                                @endforeach
                            </div>
                            @error('responses.'.$key)
                                <p id="erro-{{ $key }}" class="text-sm font-semibold text-red-700">Escolha uma opção para esta frase.</p>
                            @enderror
                        </fieldset>
                    @endforeach
                </section>
            @endforeach

            <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                <a href="{{ url('/') }}" class="text-[15px] font-semibold text-emerald-800 hover:underline underline-offset-4">Responder depois</a>
                <button type="submit" class="min-h-[52px] px-7 rounded-xl bg-emerald-700 text-white text-base font-bold hover:bg-emerald-800">Enviar respostas</button>
            </div>
        </form>
    </div>
</div>
