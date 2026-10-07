# SisREAd

Sistema de recomendação de Recursos Educacionais Abertos (REA). Laravel 11 + Livewire 3 + SQLite.
Documentação completa em `docs/` (comece por `docs/README.md` e `docs/arquitetura.md`).

## Comandos
- Setup: `powershell -ExecutionPolicy Bypass -File scripts\setup.ps1`
- Rodar: `php artisan serve` **e** `php artisan queue:work --timeout=600` (sem o worker, as buscas nunca terminam)
- Testes: `php artisan test`
- Formatação: `vendor/bin/pint`
- Composer no Windows: sempre com `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix`

## Onde está o quê
- UI e orquestração da busca: `app/Livewire/FindREA.php` + `resources/views/livewire/find-r-e-a.blade.php`
- Busca em repositórios externos: `app/Jobs/Process{Aquarela,MecRed,Eduplay}.php`
- Questionário de metas (EMAPRE): `app/Livewire/Emapre.php`
- URL do MEC RED: `app/Helpers/helpers.php`
- Regras, ordenação e textos das explicações: `app/Recommendation/` (spec em `docs/transparencia.md`)
- Classificação de meta por LLM (os três jobs): `app/Recommendation/MetaClassifier.php` + `app/Recommendation/Llm/`, configuração em `config/llm.php` (`docs/integracoes.md`)
- Feature flags de explicabilidade e grupos do experimento: `app/Experimento/Experimento.php` + `config/experimento.php`, diretiva `@explicabilidade` (`docs/feature-flags.md`)
- Correções do usuário (escrutabilidade): `app/Recommendation/UserCorrections.php` + ações no `FindREA` (`docs/plano-escrutabilidade.md`)

## Convenções
- Textos de UI, comentários e mensagens de validação em português.
- Os jobs se ligam à busca por `Data.searched_at`; mantenha essa chave ao mexer no fluxo.
- Rótulos de recomendação (`both`, `profile`, `interest`, `meta_*`) são faixas do grau (`RuleClassifier::PESOS`) e estão acoplados a `Ranking` e `ExplanationRenderer::FAIXAS`. Mude juntos. Nenhum repositório tem rótulo fixo.
- Toda regra nova de recomendação deve gravar seu critério em `explicacao` (decisão e explicação vêm da mesma fonte).
- Os jobs gravam `chave` em cada REA; sem ela o REA não aceita correção. Depois de mexer nos jobs, rode `php artisan queue:restart`.
- `database/database.sqlite` **não** é versionado (tem dados reais). É criado por `scripts/setup.ps1`; não rode `migrate:fresh` num banco com dados sem pedir.
- Funcionalidade nova de explicabilidade entra atrás de uma flag (`Experimento::FLAGS` + grupos em `config/experimento.php`), escondendo a UI **e** recusando a ação no servidor. Recomendação e ordenação não dependem do grupo.
- Bugs conhecidos estão em `docs/problemas-conhecidos.md`. Atualize esse arquivo ao corrigir algum.
