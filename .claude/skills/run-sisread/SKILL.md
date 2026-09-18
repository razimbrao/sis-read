---
name: run-sisread
description: Sobe o SisREAd localmente (servidor + worker de fila) e verifica uma busca de ponta a ponta. Use ao rodar, testar manualmente ou tirar screenshot do app.
---

1. Se `vendor/` ou `.env` não existirem, rode `powershell -ExecutionPolicy Bypass -File scripts\setup.ps1`.
2. Em background: `php artisan serve --host=127.0.0.1 --port=8000`
3. Em background: `php artisan queue:work --timeout=600 --tries=3`
4. Abra http://127.0.0.1:8000, clique em **Usuário**, preencha perfil `Ensino médio` e interesse `algoritmos` e clique em **Buscar**.
5. Confirme que o worker imprime `ProcessAquarela/ProcessMecRed/ProcessEduplay ... DONE` e que a tabela de resultados aparece.
6. Se falhar, veja `storage/logs/laravel.log` e `php artisan queue:failed`.
