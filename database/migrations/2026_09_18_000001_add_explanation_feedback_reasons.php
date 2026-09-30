<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FRASES = [
        'As explicações das recomendações estavam erradas ou confusas.',
        'As explicações não ajudaram a entender a ordem dos resultados.',
    ];

    public function up(): void
    {
        foreach (self::FRASES as $frase) {
            if (! DB::table('feedback_reasons')->where('phrase', $frase)->exists()) {
                DB::table('feedback_reasons')->insert([
                    'phrase' => $frase,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('feedback_reasons')->whereIn('phrase', self::FRASES)->delete();
    }
};
