<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Data;
use App\Jobs\ProcessAquarela;
use App\Jobs\ProcessMecRed;
use App\Jobs\ProcessEduplay;

class RunRecommendationSimulation extends Command
{
    // Nome do comando para chamar no terminal
    protected $signature = 'simulation:run-all';

    protected $description = 'Dispara os Jobs de REAs para todas as combinações de perfil, interesse e metas pedagógicas.';

    public function handle()
    {
        // 📋 1. Mapeamento exato da sua matriz de Perfil x Interesse
        $matrix = [
            'educacao infantil' => [
                'reconhecimento de padrões', 'lógica', 'robótica', 'algoritmos', 'abstração'
            ],
            'ensino fundamental' => [
                'algoritmos', 'decomposição', 'lógica de programação', 'pensamento computacional', 
                'robótica', 'reconhecimento de padrões', 'abstração'
            ],
            'ensino medio' => [
                'algoritmos', 'programação', 'abstração', 'decomposição', 'lógica', 
                'estruturas de dados', 'robótica'
            ],
            'ensino superior' => [
                'programação', 'estruturas de dados', 'abstração', 'algoritmos', 
                'decomposição', 'lógica de programação', 'pensamento computacional'
            ]
        ];

        // 🎯 2. Todas as variações de metas (incluindo null para usuários sem meta ativa)
        $metas = [null, 'ma', 'mpa', 'mpe'];

        // 📑 3. Tipos padrão para a busca (Ajuste se o seu sistema exigir extensões diferentes)
        $types = ['texto', 'video', 'software', 'audio'];

        $totalDisparados = 0;
        $counter = 1;

        $this->info('🚀 Iniciando a simulação em massa do ecossistema...');

        foreach ($matrix as $profile => $interests) {
            foreach ($interests as $interest) {
                foreach ($metas as $meta) {
                    
                    // 🔑 Cria um identificador único temporal para esta combinação exata
                    // Isso garante que um Job não sobrescreva os dados ou métricas do outro
                    $timestampSession = time() . '_' . $counter;

                    // 🛠️ SALVAGUARDA: Cria o registro inicial na tabela Data.
                    // Se não fizermos isso, os Jobs falharão ao tentar dar ->update() em um registro inexistente.
                    Data::create([
                        'searched_at' => $timestampSession,
                        'data'        => json_encode([]),
                        'finished'    => false,
                        'time'        => 0
                    ]);

                    // O termo de busca que vai para as APIs externas
                    $interestApiSearch = $interest;

                    // 📡 Dispara a tríade de Jobs para a fila do Laravel
                    ProcessAquarela::dispatch($interestApiSearch, $types, $profile, $timestampSession, $meta);
                    ProcessMecRed::dispatch($interestApiSearch, $types, $profile, $interest, $timestampSession, $meta);
                    ProcessEduplay::dispatch($interestApiSearch, $profile, $timestampSession, $meta, $types);

                    $metaNome = $meta ?? 'Sem Meta (Padrão)';
                    $this->line("   [✔] Enfileirado: Perfil: {$profile} | Interesse: {$interest} | Meta: {$metaNome}");
                    
                    $counter++;
                    $totalDisparados += 3; // 3 jobs por bloco
                }
            }
        }

        $this->info("✨ Sucesso! Varredura concluída. {$totalDisparados} Jobs foram enviados para a fila.");
        $this->info("Certifique-se de que o comando 'php artisan queue:work' esteja rodando para processá-los.");
    }
}