<div class="max-w-md mx-auto px-4 sm:px-6 py-12">
    <section class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-6">
        <div class="space-y-1.5">
            <h1 class="text-2xl font-extrabold tracking-tight">Entrar</h1>
            <p class="text-[15px] text-slate-600">Use a conta para salvar suas preferências e a sua meta de aprendizagem. Você também pode <a href="{{ url('/') }}" class="font-semibold text-emerald-800 underline underline-offset-4">buscar sem conta</a>.</p>
        </div>

        <form wire:submit.prevent="login" class="space-y-4" x-data="{ verSenha: false }">
            <div>
                <label for="email" class="block text-[15px] font-semibold mb-1.5">E-mail</label>
                <input id="email" type="email" autocomplete="email" wire:model="email"
                    @error('email') aria-invalid="true" aria-describedby="erro-email" @enderror
                    class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-base">
                @error('email') <p id="erro-email" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-[15px] font-semibold mb-1.5">Senha</label>
                <div class="flex rounded-xl border border-slate-300 overflow-hidden focus-within:border-emerald-700">
                    <input id="password" :type="verSenha ? 'text' : 'password'" type="password" autocomplete="current-password" wire:model="password"
                        @error('password') aria-invalid="true" aria-describedby="erro-senha" @enderror
                        class="flex-1 min-w-0 px-3.5 py-3 text-base border-0 outline-none">
                    <button type="button" x-on:click="verSenha = !verSenha" class="px-3.5 text-sm font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100"
                        x-text="verSenha ? 'Esconder' : 'Mostrar'" :aria-pressed="verSenha">Mostrar</button>
                </div>
                @error('password') <p id="erro-senha" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="w-full min-h-[52px] rounded-xl bg-emerald-700 text-white text-base font-bold hover:bg-emerald-800">Entrar</button>
        </form>

        <p class="text-[15px] text-center text-slate-600">
            Não tem conta? <a href="{{ route('register') }}" class="font-semibold text-emerald-800 underline underline-offset-4">Criar conta</a>
        </p>
    </section>
</div>
