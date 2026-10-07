{{-- Enquanto algum repositório não respondeu. O detalhe por repositório fica atrás da flag progresso-busca.
     Passado o limite de tempo, o aviso de busca travada aparece acima do progresso. --}}
@php $travada = $this->buscaTravada($repos); @endphp
@if ($travada)
    {{-- Não é explicabilidade: aparece nos três grupos; se o worker cair, o job não é refeito. --}}
    <div role="alert" class="flex flex-wrap items-center gap-4 p-5 rounded-2xl bg-white border-2 border-amber-500">
        <svg class="w-7 h-7 text-amber-700 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        <div class="flex-[1_1_320px] space-y-1">
            <p class="text-lg font-bold">A busca não terminou</p>
            <p class="text-[15px] text-slate-600">Algum repositório parou de responder. Os resultados que já chegaram continuam abaixo.</p>
        </div>
        <button type="button" wire:click="refazerBusca" wire:loading.attr="disabled"
            class="min-h-12 px-5 rounded-xl bg-emerald-700 text-white font-bold hover:bg-emerald-800 disabled:opacity-60">Buscar de novo</button>
    </div>
@endif
    <section aria-live="polite" class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 space-y-4">
        <div class="flex items-center gap-3.5">
            <span class="w-7 h-7 rounded-full border-[3px] border-emerald-100 border-t-emerald-700 animate-spin shrink-0" aria-hidden="true"></span>
            @explicabilidade('progresso-busca')
            <div>
                <p class="text-lg font-bold">Consultando repositórios… {{ $respondidos }} de {{ count($repos) }} responderam.</p>
                <p class="text-[15px] text-slate-600">
                    A lista continua crescendo até o último responder.
                    @if ($this->contexto['meta'] ?? null)
                        Com a sua meta, a IA classifica cada recurso; a primeira busca pode levar cerca de 1 minuto.
                    @endif
                </p>
            </div>
            @else
            <p class="text-lg font-bold">Carregando...</p>
            @endexplicabilidade
        </div>
        @explicabilidade('progresso-busca')
        <div role="progressbar" aria-label="Repositórios que responderam" aria-valuemin="0" aria-valuemax="{{ count($repos) }}" aria-valuenow="{{ $respondidos }}" class="h-2.5 rounded-full bg-slate-200 overflow-hidden">
            <div class="h-full bg-emerald-700 rounded-full transition-all" style="width: {{ count($repos) ? round($respondidos / count($repos) * 100) : 0 }}%"></div>
        </div>
        <ul class="grid gap-3 sm:grid-cols-3">
            @foreach ($repos as $nome => $r)
                @php $aguardando = $r['situacao'] === 'aguardando'; @endphp
                <li @class([
                    'flex items-center gap-3 p-3.5 rounded-xl border',
                    'bg-slate-50 border-slate-200' => $aguardando,
                    'bg-emerald-50 border-emerald-200' => ! $aguardando,
                ])>
                    @if ($aguardando)
                        <span class="w-[18px] h-[18px] rounded-full border-[2.5px] border-slate-200 border-t-slate-500 animate-spin shrink-0" aria-hidden="true"></span>
                    @else
                        <svg class="w-[22px] h-[22px] text-emerald-700 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                    @endif
                    <span class="flex flex-col">
                        <span class="font-bold">{{ $nome }}</span>
                        <span class="text-sm {{ $aguardando ? 'text-slate-600' : 'text-emerald-800' }}">
                            @switch($r['situacao'])
                                @case('ok') {{ $r['itens'] }} {{ $r['itens'] === 1 ? 'recurso' : 'recursos' }} @break
                                @case('parcial') {{ $r['itens'] }} recursos; parte não respondeu @break
                                @case('falhou') não respondeu @break
                                @default consultando…
                            @endswitch
                        </span>
                    </span>
                </li>
            @endforeach
        </ul>
        @endexplicabilidade
    </section>
