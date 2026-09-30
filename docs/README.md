# SisREAd — Documentação

**SisREAd** (Sistema de Recomendação para REA) recomenda Recursos Educacionais Abertos (REA) sobre
Pensamento Computacional. O usuário informa **perfil** (nível educacional, de Educação Infantil a Ensino
Superior) e **interesse** (tema). Se tiver conta, pode responder ao questionário **EMAPRE-U** para
identificar sua **meta de aprendizagem** (Aprender, Performance-Aproximação ou Performance-Evitação).
O sistema consulta três fontes ao mesmo tempo (Aquarela, MEC RED e Eduplay), classifica cada REA por
regras (e, no Aquarela, por um LLM local via Ollama) e mostra os resultados ordenados por aderência,
com explicação de cada recomendação.

Projeto acadêmico UFJF / UTFPR. A base teórica e as avaliações estão na monografia de Gabriel do
Carmo Silva (UFJF, 2026), resumida em [monografia.md](monografia.md).

| Documento | Conteúdo |
|---|---|
| [monografia.md](monografia.md) | Problema, perguntas de pesquisa, fundamentos, avaliações e diferenças entre a monografia e o código |
| [uso.md](uso.md) | Telas e fluxo de uso (visitante, usuário cadastrado, colaborador, avaliação da busca) |
| [arquitetura.md](arquitetura.md) | Modelo conceitual, stack, componentes e fluxo de uma busca |
| [recomendacao.md](recomendacao.md) | EMAPRE-U, regras de classificação por repositório, ordenação e destaque |
| [transparencia.md](transparencia.md) | Explicações das recomendações (especificação técnica) |
| [plano-transparencia.md](plano-transparencia.md) | Plano e justificativa da transparência |
| [dados.md](dados.md) | Modelo de dados (tabelas SQLite) e métricas coletadas |
| [integracoes.md](integracoes.md) | APIs externas (Aquarela, MEC RED, Eduplay, Ollama, Google Sheets) |
| [setup.md](setup.md) | Instalação e execução local (Windows, sem Docker) e simulação em massa |
| [problemas-conhecidos.md](problemas-conhecidos.md) | Bugs e riscos conhecidos |
