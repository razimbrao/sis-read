{{-- Avaliação da busca: estrelas e, para notas até 3, motivos e comentário. --}}
<section class="p-5 sm:p-6 rounded-2xl bg-white border border-slate-200">
    @if ($this->feedbackSent)
        <div class="flex items-center gap-3" role="status">
            <svg class="w-7 h-7 text-emerald-700 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
            <div>
                <p class="text-lg font-bold">Feedback enviado!</p>
                <p class="text-[15px] text-slate-600">Muito obrigado por dedicar um tempo para nos ajudar a melhorar a sua experiência.</p>
            </div>
        </div>
    @else
        <div class="flex flex-wrap items-center gap-4" x-data="{ hoverRating: 0 }">
            <h2 class="flex-[1_1_240px] text-base font-bold">
                @if ($this->rating > 0)
                    Obrigado! Você avaliou com {{ $rating }} {{ $rating == 1 ? 'estrela' : 'estrelas' }}.
                @else
                    Estes resultados ajudaram você?
                @endif
            </h2>
            <div class="flex gap-1" role="group" aria-label="Nota de 1 a 5">
                @for ($i = 1; $i <= 5; $i++)
                    <button
                        type="button"
                        aria-label="{{ $i }} {{ $i === 1 ? 'estrela' : 'estrelas' }}"
                        @if ($rating === $i) aria-pressed="true" @endif
                        class="w-11 h-11 flex items-center justify-center rounded-lg hover:bg-slate-100"
                        x-on:mouseenter="hoverRating = {{ $i }}"
                        x-on:mouseleave="hoverRating = 0"
                        wire:click="setRating({{ $i }})"
                    >
                        <svg class="w-8 h-8 transition-colors" :class="hoverRating >= {{ $i }} || (!hoverRating && {{ $rating }} >= {{ $i }}) ? 'text-amber-400' : 'text-slate-300'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 3.5l2.6 5.3 5.8.8-4.2 4.1 1 5.8L12 16.8l-5.2 2.7 1-5.8-4.2-4.1 5.8-.8z"/>
                        </svg>
                    </button>
                @endfor
            </div>
        </div>

        @if ($this->rating > 0)
            <div class="mt-5 pt-5 border-t border-slate-100 space-y-4">
                @if ($this->rating <= 3)
                    <fieldset>
                        <legend class="text-[15px] font-semibold mb-2">O que se destacou?</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->motivosFeedback() as $reason)
                                <label class="cursor-pointer">
                                    <input type="checkbox" wire:model="selectedReasons" value="{{ $reason->id }}" class="peer sr-only">
                                    <span class="inline-flex px-3.5 py-2 text-sm rounded-full border border-slate-300 text-slate-700 bg-white peer-checked:bg-slate-900 peer-checked:text-white peer-checked:border-slate-900 peer-focus-visible:outline peer-focus-visible:outline-[3px] peer-focus-visible:outline-emerald-700">
                                        {{ $reason->phrase }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <div>
                    <label for="comment" class="block text-[15px] font-semibold mb-2">Deixe um comentário (opcional)</label>
                    <textarea id="comment" wire:model="comment" rows="3"
                        class="w-full rounded-xl border border-slate-300 text-base p-3 resize-y"
                        placeholder="Conte mais detalhes sobre sua experiência..."></textarea>
                </div>

                @error('feedback_vazio')
                    <p class="text-[15px] font-medium text-red-700" role="alert">{{ $message }}</p>
                @enderror
                <button wire:click="saveSearchFeedback" type="button"
                    class="min-h-12 px-6 rounded-xl bg-emerald-700 text-white font-bold hover:bg-emerald-800">Enviar avaliação</button>
            </div>
        @endif
    @endif
</section>
