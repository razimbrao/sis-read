{{-- Sugestão livre sobre o sistema (tabela feedbacks). --}}
<section class="max-w-2xl mx-auto px-4 sm:px-6 py-10 space-y-6">
    <div class="space-y-2">
        <h1 class="text-3xl font-extrabold tracking-tight">Enviar sugestão</h1>
        <p class="text-base leading-relaxed text-slate-600">Encontrou um problema ou tem uma ideia para o SisREAd? Escreva aqui.</p>
    </div>
    <form wire:submit="sendFeedback" class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 space-y-4">
        <div>
            <label for="message" class="block text-[15px] font-semibold mb-1.5">@lang('Deixe seu feedback')</label>
            <textarea id="message" maxlength="4096" wire:model.live="message" rows="8" aria-describedby="contador-mensagem"
                class="w-full rounded-xl border border-slate-300 p-3.5 text-base resize-y"></textarea>
            <div class="mt-1 flex justify-between gap-3 text-sm">
                <span class="text-red-700">@error('message') {{ $message }} @enderror</span>
                <span id="contador-mensagem" class="text-slate-600">{{ $charCount }} de 4096 caracteres</span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <button type="submit" class="min-h-12 px-6 rounded-xl bg-emerald-700 text-white font-bold hover:bg-emerald-800">@lang('Enviar')</button>
            <a href="{{ url('/') }}" class="text-[15px] font-semibold text-emerald-800 hover:underline underline-offset-4">Voltar à busca</a>
            @if ($this->showMessage)
                <p class="text-[15px] font-medium text-green-800" role="status">✓ @lang('Feedback enviado com sucesso!')</p>
            @endif
        </div>
    </form>
</section>
