{{-- Layout das telas Livewire de página inteira (login, cadastro, EMAPRE, preferências). --}}
<x-site :title="$title ?? null">
    {{ $slot }}
</x-site>
