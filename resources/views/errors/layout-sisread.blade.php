{{-- Modelo das páginas de erro. Não usa o cabeçalho com sessão/rotas, que podem não estar disponíveis num erro. --}}
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $titulo }} · SisREAd</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif; }
            :focus-visible { outline: 3px solid #047857; outline-offset: 2px; border-radius: 6px; }
        </style>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased flex items-center justify-center px-4">
        <main class="max-w-xl w-full bg-white border border-slate-200 rounded-2xl px-6 py-12 sm:px-10 flex flex-col items-center text-center gap-4">
            <span class="w-11 h-11 rounded-xl bg-emerald-700 flex items-center justify-center" aria-hidden="true">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/><path d="M9 8h6M9 12h4"/></svg>
            </span>
            <p class="text-sm font-bold tracking-widest text-slate-600">{{ $rotulo }}</p>
            <h1 class="text-3xl font-extrabold tracking-tight">{{ $titulo }}</h1>
            <p class="text-lg text-slate-600 leading-relaxed">{{ $texto }}</p>
            <div class="flex flex-wrap justify-center gap-3 mt-2">
                @if ($recarregar ?? false)
                    <button type="button" onclick="location.reload()" class="inline-flex items-center min-h-12 px-5 rounded-xl bg-emerald-700 text-white font-bold hover:bg-emerald-800">Recarregar a página</button>
                @endif
                <a href="{{ url('/') }}" @class([
                    'inline-flex items-center min-h-12 px-5 rounded-xl font-semibold',
                    'border border-slate-300 text-slate-900 hover:bg-slate-50' => $recarregar ?? false,
                    'bg-emerald-700 text-white font-bold hover:bg-emerald-800' => ! ($recarregar ?? false),
                ])>Ir para o início</a>
            </div>
        </main>
    </body>
</html>
