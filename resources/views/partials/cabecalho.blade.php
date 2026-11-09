@php
    $secao = request()->is('/') ? (request()->query('secao') ?: 'buscar') : null;
    $itens = [
        // No celular os três itens dividem a largura: o rótulo curto evita esconder algum atrás de rolagem.
        'buscar' => ['Buscar recursos', comParams(url('/')), 'Buscar'],
        'colaborar' => ['Contribuir com um REA', comParams(url('/?secao=colaborar')), 'Contribuir'],
        'sugestao' => ['Enviar sugestão', comParams(url('/?secao=sugestao')), 'Sugestão'],
    ];
@endphp
<header class="bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-2 sm:py-3 flex flex-wrap items-center gap-x-2 sm:gap-x-6 gap-y-1 sm:gap-y-3">
        <a href="{{ comParams(url('/')) }}" class="flex items-center gap-2 sm:gap-2.5 min-h-11 text-slate-900 rounded-lg">
            <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-[10px] bg-emerald-700 flex items-center justify-center" aria-hidden="true">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/><path d="M9 8h6M9 12h4"/></svg>
            </span>
            <span class="flex flex-col leading-tight">
                <span class="font-extrabold text-base sm:text-lg tracking-tight">SisREAd</span>
                <span class="hidden sm:block text-xs text-slate-600">Recursos Educacionais Abertos</span>
            </span>
        </a>

        <nav aria-label="Principal" class="order-3 w-full lg:order-none lg:w-auto lg:flex-1 grid grid-cols-3 gap-1 sm:flex">
            @foreach ($itens as $chave => [$rotulo, $href, $curto])
                <a href="{{ $href }}" aria-label="{{ $rotulo }}"
                   @if ($secao === $chave) aria-current="page" @endif
                   @class([
                       'inline-flex items-center justify-center min-h-11 px-1 sm:px-3.5 rounded-lg text-sm sm:text-[15px] whitespace-nowrap transition-colors',
                       'bg-emerald-50 text-emerald-800 font-semibold' => $secao === $chave,
                       'text-slate-700 font-medium hover:bg-slate-100' => $secao !== $chave,
                   ])><span class="sm:hidden">{{ $curto }}</span><span class="hidden sm:inline">{{ $rotulo }}</span></a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-1 sm:gap-2">
            @auth
                <a href="{{ comParams(route('preferencias')) }}" aria-label="Minha conta"
                   @if (request()->routeIs('preferencias')) aria-current="page" @endif
                   class="flex items-center gap-2 min-h-11 pl-1.5 pr-3 py-1.5 whitespace-nowrap rounded-full border border-slate-200 text-[15px] font-semibold text-slate-900 hover:bg-slate-50">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs font-bold" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    <span class="sm:hidden">Conta</span><span class="hidden sm:inline">Minha conta</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="min-h-11 px-3 rounded-lg text-[15px] font-medium text-slate-700 hover:bg-slate-100">Sair</button>
                </form>
            @else
                <a href="{{ comParams(route('login')) }}" class="inline-flex items-center min-h-11 whitespace-nowrap px-2.5 sm:px-4 rounded-lg border border-slate-300 text-sm sm:text-[15px] font-semibold text-slate-900 hover:bg-slate-50">Entrar</a>
                <a href="{{ comParams(route('register')) }}" class="inline-flex items-center min-h-11 whitespace-nowrap px-2.5 sm:px-4 rounded-lg bg-emerald-700 text-sm sm:text-[15px] font-semibold text-white hover:bg-emerald-800">Criar conta</a>
            @endauth
        </div>
    </div>
</header>
