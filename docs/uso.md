# Telas e fluxo de uso

Toda a interface fica em uma página (`/`, `resources/views/welcome.blade.php` +
`app/Livewire/FindREA.php`), com exceção do login, do cadastro e do questionário EMAPRE-U.

## 1. Tela inicial (`/`)

- Botões **Colaborador**, **Usuário** e **Deixe seu feedback** (`FindREA::selectUserType`).
- Visitante sem login: botões **Login** (`/login`) e **Registrar** (`/register`). Sem conta, a busca
  funciona, mas a meta de aprendizagem não é usada.
- Usuário logado: nome e, abaixo dele, **Responda o questionário** (se ainda não respondeu) ou a meta
  dominante (Meta Aprender, Meta Performance-Aproximação ou Meta Performance-Evitação).
- Barra lateral **Pesquisas mais acessadas**: pares perfil/interesse mais buscados (tabela `searches`).

## 2. Cadastro, login e questionário EMAPRE-U

1. `/register` (`App\Livewire\Auth\Register`) cria a conta e `/login` (`Auth\Login`) autentica.
2. Após o login, o usuário pode ir para a busca direto (só perfil e interesse) ou responder ao
   questionário em `/emapre` (`App\Livewire\Emapre`, exige login).
3. O questionário tem 28 afirmações em escala Likert de 1 a 5. O cálculo está em
   [recomendacao.md](recomendacao.md#emapre-u-meta-de-aprendizagem-do-usuário).
4. A meta dominante fica gravada em `questionnaires` e aparece na tela inicial. **Não é possível
   refazer o questionário**: o botão some depois da primeira resposta (a monografia lista isso como
   trabalho futuro).

## 3. Busca (botão **Usuário**)

- Campos de texto livre **Perfil** (esperado: Educação infantil, Ensino fundamental, Ensino médio ou
  Ensino superior) e **Interesse** (tema de Pensamento Computacional). O interesse precisa coincidir,
  sem diferença de acento ou maiúsculas, com uma opção conhecida em `FindREA::$interestOptions`: hoje só
  *algoritmos*, *decomposição*, *reconhecimento de padrões* e *abstração*. Se não coincidir, a busca não
  roda direito (problema conhecido #14).
- Ao buscar, o sistema enfileira um job por repositório e a tela consulta o resultado a cada ~2 s
  (`wire:poll`) até os jobs terminarem. Detalhes em [arquitetura.md](arquitetura.md#fluxo-de-uma-busca).
- Resultado: tabela paginada (10 por página) com título, link, repositório e **selo da faixa**
  (Nível e tipo, Nível, Tema, e equivalentes com meta). As linhas da faixa mais alta ganham fundo
  amarelo (destaque). Ver [recomendacao.md](recomendacao.md#destaque-highlight).
- Explicações: **Por que este REA?** em cada linha e os painéis **Como ordenamos** e **O que usamos
  sobre você** ([transparencia.md](transparencia.md)).

Exemplos da monografia: perfil *Ensino médio*, interesse *Algoritmos*, meta Performance-Evitação; e
perfil *Ensino superior*, interesse *Reconhecimento de padrões*, meta Aprender.

## 4. Avaliação da busca

- Nota de 1 a 5 estrelas (`FindREA::setRating`, grava `data.stars`).
- A área mostra o total de REAs retornados e a **Eficiência** (REAs por segundo = total ÷ `data.time`).
- Com **3 estrelas ou menos**, aparecem os motivos de insatisfação (tabela `feedback_reasons`,
  inclusive dois motivos sobre as explicações).
- Sempre há um campo de comentário livre. Motivos e comentário vão para `data_reasons`
  (`FindREA::saveSearchFeedback`). É preciso escolher ao menos um motivo ou escrever um comentário.

## 5. Colaborador

Formulário para cadastrar um REA: nome, função, instituição, título do REA, referência (link),
perfil, interesse e **item** (tipo de conteúdo). Grava em `collaborators` (`FindREA::insert`).

Efeito na recomendação: os tipos (`item`) cadastrados viram **tipos preferidos**. Um REA do Aquarela
cujo `tipoConteudo` coincide com um tipo preferido atende ao critério *tipo* e pode subir para a faixa
`both`. É isso que personaliza a busca de quem não tem meta. A intenção é que os interesses cadastrados
também virem opções de busca, mas isso não funciona hoje (problema conhecido #19). Ver
[recomendacao.md](recomendacao.md).

## 6. Feedback geral

**Deixe seu feedback** abre um texto livre (até 4096 caracteres) sobre o sistema, gravado em
`feedbacks` (`FindREA::sendFeedback`).
