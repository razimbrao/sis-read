{{-- Notificação temporária depois de uma correção (FindREA::$notificacao). A lista se reordena e o REA
     corrigido some de vista; a notificação diz para onde ele foi, se a faixa mudou, e leva até ele.
     Posição: no celular, embaixo e na largura toda (respeitando a área segura); no desktop, no canto
     inferior direito. Fica abaixo do modal de tipos (z-40 contra z-50).
     wire:ignore + chave por id: o poll re-renderiza a página a cada 2 s, e sem isso o tempo e o estado
     (visível ou não) recomeçariam a cada re-render. Uma correção nova traz um id novo. --}}
<div
    wire:ignore
    wire:key="notificacao-{{ $notificacao['id'] }}"
    x-data="{
        visivel: false,
        timer: null,
        agendar() { clearTimeout(this.timer); this.timer = setTimeout(() => this.visivel = false, 8000); },
        pausar() { clearTimeout(this.timer); },
        async ver() {
            const alvo = @js($notificacao['alvo']);
            if (! document.getElementById(alvo)) {
                await $wire.irParaPagina(@js($notificacao['pagina']));
            }
            const cartao = document.getElementById(alvo);
            this.visivel = false;
            if (! cartao) return;
            cartao.scrollIntoView({ behavior: 'smooth', block: 'center' });
            cartao.setAttribute('tabindex', '-1');
            cartao.focus({ preventScroll: true });
            cartao.classList.add('ring-4', 'ring-amber-300');
            setTimeout(() => cartao.classList.remove('ring-4', 'ring-amber-300'), 2500);
        },
    }"
    x-init="$nextTick(() => { visivel = true; agendar(); })"
    @mouseenter="pausar()"
    @mouseleave="agendar()"
    @focusin="pausar()"
    @focusout="agendar()"
    class="fixed z-40 inset-x-4 sm:inset-x-auto sm:right-6 sm:w-96"
    style="bottom: calc(1rem + env(safe-area-inset-bottom))"
    role="status"
    aria-live="polite"
>
    <div x-show="visivel" x-transition.opacity style="display: none" class="flex items-start gap-3 p-4 rounded-xl bg-slate-900 text-white shadow-lg text-[15px]">
        <span class="text-amber-300" aria-hidden="true">{{ \App\Recommendation\ExplanationRenderer::MARCA_CORRECAO }}</span>
        <div class="flex-1 min-w-0 break-words">
            <p>{{ $notificacao['texto'] }}</p>
            @if ($notificacao['detalhe'])
                <p class="mt-1 text-sm text-slate-300">{{ $notificacao['detalhe'] }}</p>
            @endif
            @if ($notificacao['alvo'])
                <button type="button" class="mt-1 min-h-11 font-semibold text-amber-300 underline underline-offset-2" @click="ver()">Ver na lista</button>
            @endif
        </div>
        <button type="button" class="shrink-0 -m-2 min-w-11 min-h-11 rounded-lg text-xl leading-none text-slate-300 hover:text-white" aria-label="Fechar notificação" @click="visivel = false">&times;</button>
    </div>
</div>
