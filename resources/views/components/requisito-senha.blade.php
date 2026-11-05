{{-- Um requisito da senha. `cumprido` é uma expressão Alpine avaliada no x-data do formulário. --}}
@props(['cumprido'])
<li class="flex items-center gap-2 text-sm" x-bind:class="({{ $cumprido }}) ? 'text-emerald-800 font-semibold' : 'text-slate-600'">
    <span aria-hidden="true" class="inline-flex items-center justify-center w-5 h-5 shrink-0 rounded-full border text-xs font-bold"
        x-bind:class="({{ $cumprido }}) ? 'bg-emerald-700 border-emerald-700 text-white' : 'border-slate-400 text-transparent'">✓</span>
    <span>{{ $slot }}</span>
    <span class="sr-only" x-text="({{ $cumprido }}) ? '(cumprido)' : '(pendente)'">(pendente)</span>
</li>
