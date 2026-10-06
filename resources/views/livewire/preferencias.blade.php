{{-- Minha conta / Preferências. Tela funcional e simples; o visual final vem do redesign. --}}
<div>
    <h1 class="text-2xl font-bold mb-1 text-center">Minhas preferências</h1>
    <p class="text-sm text-gray-600 text-center mb-6">{{ auth()->user()->name }}</p>

    <form wire:submit.prevent="salvar" class="space-y-4">
        <fieldset>
            <legend class="font-semibold text-gray-900">Tipos de recurso preferidos</legend>
            <p class="text-sm text-gray-600 mt-1">
                Valem em todas as suas buscas. Um REA com um destes tipos sobe para a faixa “Nível e tipo”
                quando o nível também bate com o seu perfil.
                @if (! auth()->user()->tipos_preferidos)
                    Hoje você não tem preferência salva: o SisREAd usa os tipos cadastrados pelos colaboradores.
                @endif
            </p>

            @if ($opcoes)
                <div class="mt-3 space-y-1 max-h-72 overflow-y-auto border border-gray-200 rounded p-2">
                    @foreach ($opcoes as $tipo => $origens)
                        <label class="flex items-start gap-2 text-sm" wire:key="opcao-{{ md5($tipo) }}">
                            <input type="checkbox" value="{{ $tipo }}" wire:model="tipos" class="mt-1 rounded border-gray-300">
                            <span>
                                <span class="font-medium text-gray-900">{{ $tipo }}</span>
                                <span class="text-xs text-gray-500">({{ implode('; ', $origens) }})</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 mt-3">Ainda não há tipos para escolher. Faça uma busca primeiro.</p>
            @endif
            @error('tipos') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </fieldset>

        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" wire:model="incluirColaboradores" class="mt-1 rounded border-gray-300">
            <span>
                Incluir também os tipos sugeridos pelos colaboradores
                <span class="block text-xs text-gray-500">Sem esta opção, os seus tipos substituem os dos colaboradores.</span>
            </span>
        </label>

        @if ($salvo)
            <p class="text-sm text-emerald-700 bg-emerald-50 rounded px-3 py-2" role="status">
                Preferências salvas. Valem a partir da próxima busca.
            </p>
        @endif

        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
            Salvar
        </button>

        @if (auth()->user()->tipos_preferidos)
            <button type="button" wire:click="remover" class="w-full text-sm text-blue-600 hover:underline">
                Remover minha preferência (voltar aos tipos dos colaboradores)
            </button>
        @endif
    </form>

    <p class="text-center text-sm mt-6">
        <a href="{{ url('/') }}" class="text-blue-600 hover:underline">Voltar à busca</a>
    </p>
</div>
