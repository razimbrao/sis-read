<div class="max-w-md mx-auto px-4 sm:px-6 py-12">
    <section class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-6">
        <div class="space-y-1.5">
            <h1 class="text-2xl font-extrabold tracking-tight">Criar conta</h1>
            <p class="text-[15px] text-slate-600">É gratuito. Com a conta, você salva os tipos de recurso preferidos e responde o questionário de metas. Veja <a href="{{ route('privacidade') }}" class="font-semibold text-emerald-800 underline underline-offset-4">o que guardamos</a>.</p>
        </div>

        @php $campo = 'w-full rounded-xl border border-slate-300 px-3.5 py-3 text-base'; @endphp
        {{--
            A conferência fica no navegador: a senha só vai ao servidor ao enviar o formulário.
            Os métodos erro*() dizem se o erro devolvido pelo servidor ainda vale: pelo que foi enviado, sabem
            qual regra falhou (são as mesmas de Register::register) e escondem a mensagem quando ela é cumprida.
            O servidor continua sendo quem decide: um novo envio valida tudo de novo.
        --}}
        <form wire:submit.prevent="register" class="space-y-4"
            x-on:submit="enviado = { email, senha }"
            x-data="{
                nome: '', email: '', senha: '', confirmacao: '', enviado: { email: '', senha: '' },
                tamanho(texto) { return [...texto].length },
                erroNome() { return this.nome.trim() === '' || this.nome.length > 255 },
                erroEmail() {
                    if (this.enviado.email === '') return this.email === ''
                    return this.email === this.enviado.email || ! this.$refs.email.validity.valid
                },
                erroSenha() {
                    if (this.enviado.senha === '') return this.senha === ''
                    if (this.tamanho(this.enviado.senha) < {{ $senhaMinima }}) return this.tamanho(this.senha) < {{ $senhaMinima }}
                    return this.senha !== this.confirmacao
                },
            }">
            <div>
                <label for="name" class="block text-[15px] font-semibold mb-1.5">Nome</label>
                <input id="name" type="text" autocomplete="name" wire:model="name" x-on:input="nome = $event.target.value" class="{{ $campo }}" @error('name') aria-invalid="true" @enderror>
                @error('name') <p class="mt-1 text-sm text-red-700" role="alert" x-bind:hidden="! erroNome()" x-effect="$el.previousElementSibling.setAttribute('aria-invalid', erroNome())">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email" class="block text-[15px] font-semibold mb-1.5">E-mail</label>
                <input id="email" type="email" autocomplete="email" wire:model="email" x-ref="email" x-on:input="email = $event.target.value" class="{{ $campo }}" @error('email') aria-invalid="true" @enderror>
                @error('email') <p class="mt-1 text-sm text-red-700" role="alert" x-bind:hidden="! erroEmail()" x-effect="$el.previousElementSibling.setAttribute('aria-invalid', erroEmail())">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="block text-[15px] font-semibold mb-1.5">Senha</label>
                <input id="password" type="password" autocomplete="new-password" wire:model="password" x-on:input="senha = $event.target.value" aria-describedby="requisito-tamanho" class="{{ $campo }}" @error('password') aria-invalid="true" @enderror>
                @error('password') <p class="mt-1 text-sm text-red-700" role="alert" x-bind:hidden="! erroSenha()" x-effect="$el.previousElementSibling.setAttribute('aria-invalid', erroSenha())">{{ $message }}</p> @enderror
                <ul id="requisito-tamanho" class="mt-2">
                    <x-requisito-senha cumprido="[...senha].length >= {{ $senhaMinima }}">Pelo menos {{ $senhaMinima }} caracteres</x-requisito-senha>
                </ul>
            </div>
            <div>
                <label for="password_confirmation" class="block text-[15px] font-semibold mb-1.5">Repita a senha</label>
                <input id="password_confirmation" type="password" autocomplete="new-password" wire:model="password_confirmation" x-on:input="confirmacao = $event.target.value" aria-describedby="requisito-igual" class="{{ $campo }}">
                <ul id="requisito-igual" class="mt-2">
                    <x-requisito-senha cumprido="senha !== '' && senha === confirmacao">As duas senhas são iguais</x-requisito-senha>
                </ul>
            </div>

            <button type="submit" class="w-full min-h-[52px] rounded-xl bg-emerald-700 text-white text-base font-bold hover:bg-emerald-800">Criar conta</button>
        </form>

        <p class="text-[15px] text-center text-slate-600">
            Já tem conta? <a href="{{ route('login') }}" class="font-semibold text-emerald-800 underline underline-offset-4">Entrar</a>
        </p>
    </section>
</div>
