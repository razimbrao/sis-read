# Sobe o servidor web (http://127.0.0.1:8000) e o worker de fila em janelas separadas.
Set-Location (Split-Path $PSScriptRoot -Parent)
Start-Process powershell -ArgumentList '-NoExit','-Command','php artisan queue:work --timeout=600 --tries=3'
php artisan serve --host=127.0.0.1 --port=8000
