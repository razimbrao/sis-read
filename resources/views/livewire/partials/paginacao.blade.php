@php
    $atual = $pagina->currentPage();
    $ultima = $pagina->lastPage();
    // Primeira, última e duas vizinhas da atual; o resto vira reticências.
    $numeros = collect([1, $ultima, ...range(max(1, $atual - 2), min($ultima, $atual + 2))])
        ->unique()->sort()->values();
    $irParaLista = "document.getElementById('lista-reas')?.scrollIntoView({ behavior: 'smooth' })";
    $botao = 'inline-flex items-center justify-center gap-1 h-11 min-w-11 px-3 rounded-xl border border-slate-300 bg-white text-[15px] text-slate-900 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed';
@endphp
{{-- No celular, os números não cabem com alvos de 44px: fica "anterior, página X de Y, próxima". --}}
<nav class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2" aria-label="Paginação dos REAs">
    <p class="text-[15px] text-slate-600">
        Mostrando <strong class="text-slate-900">{{ $pagina->firstItem() }}–{{ $pagina->lastItem() }}</strong>
        de <strong class="text-slate-900">{{ $pagina->total() }}</strong> REAs
    </p>
    <div class="flex w-full sm:w-auto sm:flex-wrap items-center justify-between sm:justify-start gap-1.5">
        <button type="button" wire:click="prevPage" x-on:click="{{ $irParaLista }}" @disabled($atual === 1) aria-label="Página anterior" class="{{ $botao }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" /></svg>
            <span class="hidden sm:inline">Anterior</span>
        </button>
        @foreach ($numeros as $i => $numero)
            @if ($i > 0 && $numero - $numeros[$i - 1] > 1)
                <span class="hidden sm:inline w-6 text-center text-slate-500" aria-hidden="true">…</span>
            @endif
            <button
                type="button"
                wire:click="irParaPagina({{ $numero }})"
                x-on:click="{{ $irParaLista }}"
                @if ($numero === $atual) aria-current="page" @endif
                @class([
                    'hidden sm:inline-block h-11 min-w-11 px-2 rounded-xl text-[15px] tabular-nums',
                    'bg-emerald-700 text-white font-bold' => $numero === $atual,
                    'border border-slate-300 bg-white text-slate-900 hover:bg-slate-50' => $numero !== $atual,
                ])
            >{{ $numero }}</button>
        @endforeach
        <span class="sm:hidden text-[15px] text-slate-900">Página <strong>{{ $atual }}</strong> de <strong>{{ $ultima }}</strong></span>
        <button type="button" wire:click="nextPage" x-on:click="{{ $irParaLista }}" @disabled($atual === $ultima) aria-label="Próxima página" class="{{ $botao }}">
            <span class="hidden sm:inline">Próxima</span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" /></svg>
        </button>
    </div>
</nav>
