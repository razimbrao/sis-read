# Base acadêmica (monografia)

> Resumo de *SisREAd: Sistema de Recomendação para REA*, monografia de Gabriel do Carmo Silva
> (Bacharelado em Ciência da Computação, UFJF, julho de 2026; orientador: Victor Ströele de Andrade
> Menezes; banca: Liamara Scortegagna e Regina Maria Maciel Braga Villela). Aqui ficam o problema, as
> decisões de projeto e os resultados. O funcionamento do código está nos outros documentos, e as
> diferenças entre o texto e o código estão na seção 6.

## 1. Problema e perguntas de pesquisa

Os repositórios de REA (RREA) costumam buscar só por palavra-chave, sem considerar o nível
educacional, as preferências ou os objetivos de quem busca. O SisREAd reúne vários repositórios em
um só lugar e ordena os resultados por perfil, interesse e **meta de aprendizagem** (motivação).

- **Pergunta principal**: é possível um recomendador de REA que use perfil, interesse e meta de
  aprendizagem do usuário, com classificação automática por modelo de linguagem, e que seja
  tecnicamente viável?
- **QS1**: é viável integrar várias fontes de REA com classificação por meta, mantendo estabilidade e
  custo computacional aceitáveis?
- **QS2**: um LLM executado localmente classifica REA por metas de realização com concordância
  próxima à de avaliadores humanos?

Objetivos específicos: coletar perfil, interesse e meta; buscar automaticamente em vários RREA;
classificar os recursos por critérios pedagógicos e motivacionais; integrar um LLM local; avaliar
tempo de resposta e relevância.

## 2. Fundamentos usados no sistema

| Conceito | Como aparece no SisREAd |
|---|---|
| Sistema de recomendação híbrido (conhecimento + conteúdo) | Regras sobre atributos do REA (nível, tipo, meta), sem filtragem colaborativa. Ver [recomendacao.md](recomendacao.md) |
| REA e RREA | Fontes: Aquarela e MEC RED (repositórios) e Eduplay (plataforma de vídeos da RNP) |
| Metas de realização, modelo **tricotômico** | Meta Aprender (**ma**), Performance-Aproximação (**mpa**) e Performance-Evitação (**mpe**). O modelo 2×2 de Elliot e McGregor (2001) foi considerado, mas não adotado |
| Escala EMAPRE-U (Santos, Alcará e Zenorini, 2013; versão de Pereira et al., 2022) | Questionário de 28 itens em `app/Livewire/Emapre.php` |
| Cold-start | A meta obtida pelo questionário enriquece o perfil sem precisar de histórico de uso |
| LLM local (Ollama) | Classifica os REAs do Aquarela por meta, sem custo de API comercial |

O que caracteriza cada meta na classificação dos recursos:

- **Aprender**: compreensão profunda, investigação, descoberta e autonomia. O processo vale mais que
  o resultado.
- **Performance-Aproximação**: demonstrar competência e obter resultados visíveis, com aplicação
  prática, produtos com critério claro de êxito, gamificação e desafios.
- **Performance-Evitação**: evitar a exposição ao erro, com nivelamento teórico, materiais
  introdutórios, estrutura bem guiada e baixo risco de falha.

## 3. Posicionamento em relação aos trabalhos relacionados

A monografia compara o SisREAd com Villalba et al. (2017, 2022), MOORS (Hajri et al., 2019),
Tarus et al. (2017), Dahdouh et al. (2019) e Pereira Júnior et al. (2023) em cinco dimensões: vários
repositórios, metas ou objetivos de aprendizagem, classificação por IA, execução local de baixo custo
e avaliação empírica. O SisREAd é o único que atende às cinco. O trabalho mais próximo é o de
Villalba-Condori et al. (2022), que une vários repositórios, mas classifica pela taxonomia SOLO
(cognitiva) com um MLP, e não por metas motivacionais com LLM.

## 4. Avaliações

### 4.1 Percepção dos usuários

- 11 participantes do DCC/UFJF, com 14 afirmações em escala Likert de 5 pontos (P01–P14).
- 63,6% usaram o sistema **sem cadastro** (sem meta). Só 36,4% responderam ao EMAPRE-U.
- Destaques: 90,9% concordaram totalmente que o sistema funcionou sem travamentos (P02); 72,7%
  concordaram totalmente que os recursos estavam alinhados ao perfil, e não só às palavras-chave (P07);
  90,9% concordaram totalmente que o uso frequente melhoraria o desempenho nos estudos (P11); 81,8%
  recomendariam o sistema (P14).
- Sugestões: busca em inglês (opcional), interface mais moderna e **mensagem quando não há recurso
  para o tema buscado**.

### 4.2 Classificação por meta com LLM (QS2)

- Corpus: 62 REAs do Aquarela (trilhas, planos de aula, jogos, exercícios e materiais teóricos). Os
  avaliadores tiveram acesso ao conteúdo completo, e não só aos metadados.
- Padrão-ouro: dois avaliadores independentes (autor e orientador) classificaram título/descrição,
  `tipoConteudo` e `dtype`. Concordaram em 54 dos 62 (Po = 0,871, Pe = 0,338, **κ de Cohen = 0,805**,
  IC 95% ≈ [0,68; 0,93]). As 8 divergências ficaram entre metas vizinhas (nunca Aprender × Evitação)
  e foram resolvidas por consenso.
- Modelo (**Llama 3.1** via Ollama) contra o padrão-ouro: 53 de 62 acertos (**acurácia 85,48%**),
  macro precision 0,87, macro recall 0,84 e **macro F1 0,85**.

| Meta | Precision | Recall | F1 |
|---|---|---|---|
| Aprender | 0,96 | 0,88 | 0,92 |
| Performance-Aproximação | 0,73 | 0,90 | 0,81 |
| Performance-Evitação | 0,92 | 0,73 | 0,81 |

- Principal erro: o modelo tende a rotular exercícios e atividades práticas como
  Performance-Aproximação (4 de 15 Evitação e 3 de 26 Aprender foram parar lá). É a mesma fronteira
  em que os avaliadores humanos divergiram.
- A tabela completa, REA a REA, está no Apêndice A da monografia.

### 4.3 Desempenho e escalabilidade (QS1)

Experimento feito com `php artisan simulation:run-all` (ver [setup.md](setup.md)): 26 pares
perfil × interesse de Pensamento Computacional × 4 variações de meta (nenhuma, ma, mpa, mpe) = 104
cenários × 3 repositórios = **312 execuções**. Os dados vêm de `search_metrics` (ver
[dados.md](dados.md)).

- **Redução de ruído**: o Aquarela devolve o maior volume bruto e o sistema descarta a maior parte.
  O Eduplay também é bastante filtrado (só vídeos, que não servem a todas as metas). No MEC RED quase
  não há descarte, porque o filtro vai na própria requisição (mas veja a seção 6).
- **Latência**: o tempo de API é baixo e estável nos três repositórios. O gargalo é o LLM no
  Aquarela, onde a classificação vira a maior parte da espera.
- **Estabilidade**: 3.288 REAs classificados pelo LLM nas 104 execuções do Aquarela com meta
  (as únicas que usam o LLM), com fração irrisória de erros ou timeouts do Ollama. A métrica mede se a
  inferência terminou, e não se o rótulo está certo.

## 5. Limitações e trabalhos futuros (segundo a monografia)

Limitações:
- A qualidade depende dos metadados dos repositórios, que o sistema não controla.
- O LLM só classifica o Aquarela. MEC RED e Eduplay usam heurísticas por tipo de conteúdo.
- Os erros do LLM se concentram na fronteira Performance-Aproximação × Performance-Evitação.
- O LLM local aumenta o tempo de resposta em relação a uma busca comum.
- A amostra de usuários é pequena, e a maioria não usou a meta. Não houve teste com professores da
  educação básica, embora o Aquarela seja voltado a ela.

Trabalhos futuros:
- Mais repositórios (inclusive YouTube) e classificação por meta também no MEC RED e no Eduplay.
- LLMs menores, quantizados ou com fine-tuning para reduzir a latência.
- Avaliações maiores, com professores e alunos, e métricas objetivas (precisão e revocação das
  recomendações).
- Melhorar UI/UX e responsividade.
- Permitir **refazer o EMAPRE-U** e trocar a meta cadastrada (hoje não é possível).

## 6. Diferenças entre a monografia e o código atual

| Tema | Monografia | Código (branch `feat/transparencia`) |
|---|---|---|
| Modelo LLM | Llama 3.1 | `gemma3:4b`, fixo em `ProcessAquarela::classificarMetaComLLM` (antes era `llama3.2`). Os números da seção 4.2 valem para o modelo da monografia. Trocar de modelo exige repetir a avaliação |
| Prompt do LLM | Texto livre com a meta | Pede JSON `{"meta": ...}` (correção D3 de [transparencia.md](transparencia.md)) |
| Eduplay | Recomendado "prioritariamente" para Meta Aprender | Faixa `meta_one` para **ma e mpa**. Para mpe e sem meta, `interest` |
| MEC RED | Filtros de nível e `object_type` vão na requisição, então os itens já chegam alinhados | A API ignora esses filtros ([problemas-conhecidos.md](problemas-conhecidos.md) #15). A explicação marca nível, tipo e meta como *não avaliados* |
| Simulação | Perfis de Educação Infantil a Ensino Superior | Os perfis da simulação estão sem acento (`ensino medio`, `educacao infantil`) e não casam com `mecRedEtapas()`, então o MEC RED rodou **sem filtro de nível** no experimento (#17) |
| Usuário sem cadastro | "Base de referência interna" com três níveis de prioridade | É a tabela `collaborators`: tipos cadastrados viram "tipos preferidos", e a ordem é `both` → `profile` → `interest` |
| Transparência | Não aborda | Explicações por item, por lista e por usuário ([transparencia.md](transparencia.md)) |
