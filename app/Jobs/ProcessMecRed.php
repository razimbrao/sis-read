<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Data;

class ProcessMecRed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $search;
    public $time;
    public array $types;
    public string $profile;
    public string $interest;
    public ?string $meta;

    public function __construct($search, $types, $profile, $interest, $time, $meta)
    {
        $this->search = $search;
        $this->types = $types;
        $this->profile = $profile;
        $this->interest = $interest;
        $this->time = $time;
        $this->meta = $meta;
    }

    public function handle()
    {
        $start_total_time = microtime(true); 
        $offset = 0;
        $allData = [];

        $metrics = [
            'api_time'        => 0,
            'items_returned'  => 0,
            'items_filtered'  => 0,
            'timeouts_errors' => 0,
            'breakdown'       => [
                'meta_both' => 0, 'meta_one' => 0, 'meta' => 0,
                'both' => 0, 'profile' => 0, 'interest' => 0,
            ]
        ];

        $model = Data::query()->where('searched_at', $this->time)->first();

        $start_api = microtime(true);
        try {
            $search = Http::withOptions(['verify' => false])
                ->timeout(15)
                ->get(getMecRedURL(str_replace(" ", "+", $this->search), $offset, $this->profile, $this->meta))
                ->json();
            
            $metrics['api_time'] = microtime(true) - $start_api;
            $metrics['items_returned'] = is_array($search) ? count($search) : 0;
        } catch (\Exception $e) {
            $metrics['api_time'] = microtime(true) - $start_api;
            $metrics['timeouts_errors']++;
            $search = [];
        }

        $interactivity = 'Não especificado';
        $interactivity_level = 'Não especificado';
        $learning_style = 'Não especificado';
        $strategy = 'Não especificado';

        if ($this->meta && ($this->meta === 'ma' || $this->meta === 'mpa')) {
            $interactivity = 'Ativo';
            $interactivity_level = 'Alto / Muito alto';
            $learning_style = 'Intuitivo / Ativo / Auditivo/Visual';
            $strategy = 'Ativa / Abstrata / Visual/Verbal';
        } elseif ($this->meta && $this->meta === 'mpe') {
            $interactivity = 'Expositivo';
            $interactivity_level = 'Baixo / Muito baixo';
            $learning_style = 'Sensorial / Reflexivo / Auditivo/Visual';
            $strategy = 'Passiva / Concreta / Visual/Verbal';
        }

        $interactivityData = [
            'interatividade'       => $interactivity,
            'nivel_interatividade' => $interactivity_level,
            'estilo_aprendizagem'  => $learning_style,
            'estrategia'           => $strategy,
        ];

        if (is_array($search)) {
            foreach ($search as $rea) {
                // MecRed devolve dados mais diretos baseados na API construída
                $recommended = $this->meta ? 'meta_both' : 'both';

                // 📊 1. Incrementa a radiografia
                if (isset($metrics['breakdown'][$recommended])) {
                    $metrics['breakdown'][$recommended]++;
                }

                // 🎯 2. Avalia relevância
                if ($this->meta) {
                    if (in_array($recommended, ['meta_both', 'meta_one', 'meta'])) {
                        $metrics['items_filtered']++;
                    }
                } else {
                    if (in_array($recommended, ['both', 'profile', 'interest'])) {
                        $metrics['items_filtered']++;
                    }
                }

                $allData[] = array_merge([
                    'title'        => $rea['name'] ?? 'Sem título',
                    'link'         => '',
                    'type'         => '',
                    'repositorio'  => 'MECRED',
                    'recommended'  => $recommended,
                    'titulo'       => $rea['name'] ?? '',
                    'descricao'    => '',
                    'tipoConteudo' => '',
                    'dtype'        => '',
                ], $interactivityData);
            }
        }

        if (!empty($allData)) {
            $existingData = $model->data ? json_decode($model->data, true) : [];
            $model->update(['data' => json_encode(array_merge($existingData, $allData))]);
        }

        $total_time = microtime(true) - $start_total_time;
        $model->update(['finished' => true, 'time' => $model->time + $total_time]);

        DB::table('search_metrics')->insert([
            'searched_at'     => $this->time,
            'repository'      => 'MecRed',
            'profile'         => $this->profile,
            'interest'        => $this->interest,
            'meta'            => $this->meta,
            'total_time'      => $total_time,
            'api_time'        => $metrics['api_time'],
            'api_calls'       => 1, // Considerando que faz apenas uma chamada na API aqui
            'items_returned'  => $metrics['items_returned'],
            'items_filtered'  => $metrics['items_filtered'],
            'timeouts_errors' => $metrics['timeouts_errors'],
            'breakdown'       => json_encode($metrics['breakdown']),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}