@props(['title' => null, 'cabecalho' => true])
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ? $title.' · SisREAd' : 'SisREAd · Recursos Educacionais Abertos' }}</title>
        @include('partials.icones')

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
        <script>
            // Estado do modal de tipos preferidos (livewire/partials/modal-tipos). Fica numa store global, e
            // não no x-data do modal, porque o poll da busca re-renderiza a página a cada 2 s: um clique que
            // coincidisse com o re-render perdia o estado e o modal não abria (intermitente).
            document.addEventListener('alpine:init', () => {
                Alpine.store('tipos', {
                    aberto: false,
                    selecionados: [],
                    salvarNaConta: false,
                    tipoRea: null,
                    origem: null,
                    abrir(tipo = null) {
                        const modal = document.getElementById('modal-tipos');
                        if (! modal) return;
                        const nomes = JSON.parse(modal.dataset.nomes || '{}');
                        this.origem = document.activeElement;
                        this.selecionados = JSON.parse(modal.dataset.atuais || '[]');
                        this.salvarNaConta = modal.dataset.salvarNaConta === '1';
                        this.tipoRea = tipo && nomes[tipo] ? tipo : null;
                        this.aberto = true;
                    },
                    fechar() {
                        this.aberto = false;
                        const origem = this.origem;
                        setTimeout(() => origem && origem.isConnected && origem.focus());
                    },
                });
            });
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
                    <a href="{{ comParams(route('privacidade')) }}" class="inline-flex items-center min-h-11 font-medium text-emerald-700 hover:text-emerald-800 underline-offset-4 hover:underline">Privacidade</a>
                    <img src="{{ asset('assets/img/ufjf.png') }}" alt="UFJF" class="h-9 w-auto">
                    <img src="{{ asset('assets/img/logo-utfpr.png') }}" alt="UTFPR" class="h-9 w-auto">
                </div>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
