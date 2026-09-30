<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FeedbackReason;

class FeedbackReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $frases = [
            'Os conteúdos recomendados não estão de acordo com o assunto pedido.',
            'Os conteúdos recomendados não estão de acordo com o nível educacional pedido.',
            'A busca demorou tempo demais para ser executada.',
            'A abordagem dos conteúdos não são condizentes com o questionário respondido.',
            'Vieram conteúdos repetitivos.',
            'A ordem de prioridade da amostragem dos conteúdos não é coerente.',
            'As explicações das recomendações estavam erradas ou confusas.',
            'As explicações não ajudaram a entender a ordem dos resultados.',
        ];

        foreach ($frases as $frase) {
            FeedbackReason::firstOrCreate([
                'phrase' => $frase,
            ]);
        }
    }
}
