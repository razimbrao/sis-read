{{-- Correção de um critério estimado (nível ou meta) de um REA. Ver docs/plano-escrutabilidade.md. --}}
@php
    use App\Recommendation\ExplanationRenderer;
    use App\Recommendation\RuleClassifier;

    $opcoes = $criterio === 'nivel'
        ? array_intersect_key(ExplanationRenderer::NIVEIS_LEGIVEIS, array_flip(RuleClassifier::NIVEIS_VALIDOS))
        : RuleClassifier::METAS;
    $acao = $criterio === 'nivel' ? 'corrigirNivel' : 'corrigirMeta';
    $pergunta = $criterio === 'nivel' ? 'Qual é a etapa deste REA?' : 'Qual é a meta deste REA?';
@endphp
<span class="inline-flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
    {{-- wire:ignore: o poll não pode fechar o formulário nem limpar a escolha enquanto o usuário edita.
         A chave muda quando o critério é corrigido ou desfeito, e aí o Livewire troca o elemento. --}}
    <span wire:ignore wire:key="{{ $prefixo }}-{{ $criterio }}-{{ $corrigido ? 'corrigido' : 'estimado' }}" x-data="{ editando: false, valor: '' }" class="inline-flex flex-wrap items-center gap-2">
        <button
            type="button"
            x-show="!editando"
            class="text-blue-600 hover:underline focus:outline-none"
            @click="editando = true; $wire.registrarExplicacao('abriu_correcao', @js($repositorio), @js($titulo), @js($faixa))"
        >{{ $corrigido ? 'Corrigir de novo' : 'Corrigir' }}</button>
        <span x-show="editando" style="display: none" class="inline-flex flex-wrap items-center gap-2">
            <select x-model="valor" aria-label="{{ $pergunta }}" class="border border-gray-300 rounded px-1 py-0.5 text-xs">
                <option value="">{{ $pergunta }}</option>
                @foreach ($opcoes as $codigo => $nome)
                    <option value="{{ $codigo }}">{{ $nome }}</option>
                @endforeach
            </select>
            <button
                type="button"
                class="px-2 py-0.5 rounded bg-blue-600 text-white disabled:opacity-50"
                :disabled="!valor"
                @click="$wire.{{ $acao }}(@js($chave), valor); editando = false; valor = ''"
            >Salvar</button>
            <button type="button" class="text-gray-600 hover:underline" @click="editando = false; valor = ''">Cancelar</button>
        </span>
    </span>
    @if ($corrigido)
        <button type="button" class="text-blue-600 hover:underline focus:outline-none" wire:click="desfazerCorrecao(@js($chave), '{{ $criterio }}')">
            Desfazer
        </button>
    @endif
</span>
