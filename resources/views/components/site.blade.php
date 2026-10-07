@props(['title' => null, 'cabecalho' => true])
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ? $title.' · SisREAd' : 'SisREAd · Recursos Educacionais Abertos' }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: { sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    },
                },
            };
        </script>
        <style>
            /* Foco sempre visível para quem navega pelo teclado. */
            :focus-visible { outline: 3px solid #047857; outline-offset: 2px; border-radius: 6px; }
            [x-cloak] { display: none !important; }
            @media (prefers-reduced-motion: reduce) {
                *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; scroll-behavior: auto !important; }
            }
        </style>
        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased flex flex-col">
        <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:px-4 focus:py-2 focus:rounded-lg">Pular para o conteúdo</a>

        @if ($cabecalho)
            @include('partials.cabecalho')
        @endif

        <main id="conteudo" class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-5 flex flex-wrap items-center justify-between gap-4 text-sm text-slate-600">
                <span>Projeto acadêmico da UFJF e da UTFPR</span>
                <div class="flex items-center gap-5">
                    <a href="{{ route('privacidade') }}" class="font-medium text-emerald-700 hover:text-emerald-800 underline-offset-4 hover:underline">Privacidade</a>
                    <img src="{{ asset('assets/img/ufjf.png') }}" alt="UFJF" class="h-9 w-auto">
                    <img src="{{ asset('assets/img/logo-utfpr.png') }}" alt="UTFPR" class="h-9 w-auto">
                </div>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
