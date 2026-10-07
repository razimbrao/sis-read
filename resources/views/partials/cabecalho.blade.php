@php
    $secao = request()->is('/') ? (request()->query('secao') ?: 'buscar') : null;
    $itens = [
        'buscar' => ['Buscar recursos', url('/')],
        'colaborar' => ['Contribuir com um REA', url('/?secao=colaborar')],
        'sugestao' => ['Enviar sugestão', url('/?secao=sugestao')],
    ];
@endphp
<header class="bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center gap-x-6 gap-y-3">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5 text-slate-900 rounded-lg">
            <span class="w-9 h-9 rounded-[10px] bg-emerald-700 flex items-center justify-center" aria-hidden="true">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/><path d="M9 8h6M9 12h4"/></svg>
            </span>
            <span class="flex flex-col leading-tight">
                <span class="font-extrabold text-lg tracking-tight">SisREAd</span>
                <span class="hidden sm:block text-xs text-slate-600">Recursos Educacionais Abertos</span>
            </span>
        </a>

        <nav aria-label="Principal" class="order-3 w-full lg:order-none lg:w-auto lg:flex-1 flex gap-1 overflow-x-auto -mx-1 px-1">
            @foreach ($itens as $chave => [$rotulo, $href])
                <a href="{{ $href }}"
                   @if ($secao === $chave) aria-current="page" @endif
                   @class([
                       'px-3.5 py-2.5 rounded-lg text-[15px] whitespace-nowrap transition-colors',
                       'bg-emerald-50 text-emerald-800 font-semibold' => $secao === $chave,
                       'text-slate-700 font-medium hover:bg-slate-100' => $secao !== $chave,
                   ])>{{ $rotulo }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2">
            @auth
                <a href="{{ route('preferencias') }}"
                   @if (request()->routeIs('preferencias')) aria-current="page" @endif
                   class="flex items-center gap-2 pl-1.5 pr-3 py-1.5 whitespace-nowrap rounded-full border border-slate-200 text-[15px] font-semibold text-slate-900 hover:bg-slate-50">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs font-bold" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    Minha conta
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="px-3 py-2.5 rounded-lg text-[15px] font-medium text-slate-700 hover:bg-slate-100">Sair</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="whitespace-nowrap px-4 py-2.5 rounded-lg border border-slate-300 text-[15px] font-semibold text-slate-900 hover:bg-slate-50">Entrar</a>
                <a href="{{ route('register') }}" class="whitespace-nowrap px-4 py-2.5 rounded-lg bg-emerald-700 text-[15px] font-semibold text-white hover:bg-emerald-800">Criar conta</a>
            @endauth
        </div>
    </div>
</header>
