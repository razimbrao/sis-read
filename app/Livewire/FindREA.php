<?php

namespace App\Livewire;

use App\Jobs\ProcessAquarela;
use App\Jobs\ProcessEduplay;
use App\Jobs\ProcessMecRed;
use App\Models\Collaborator;
use App\Models\Correction;
use App\Models\Data;
use App\Models\ExplanationEvent;
use App\Models\Feedback;
use App\Models\Searches;
use App\Recommendation\ExplanationRenderer;
use App\Recommendation\Ranking;
use App\Recommendation\RuleClassifier;
use App\Recommendation\UserCorrections;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;
use Revolution\Google\Sheets\Facades\Sheets;

class FindREA extends Component
{
    use WithPagination;

    #[Validate('required', message: 'O perfil é obrigatório.')]
    public string $profile;

    #[Validate('required', message: 'O interesse é obrigatório.')]
    public string $interest;

    #[Validate('required_if:userType,==,colaborador', message: 'O nome é obrigatório.')]
    public string $name;

    #[Validate('required_if:userType,==,colaborador', message: 'A função é obrigatória.')]
    public string $role;

    #[Validate('required_if:userType,==,colaborador', message: 'A instituição é obrigatória.')]
    public string $institution;

    #[Validate('required_if:userType,==,colaborador', message: 'O título do REA é obrigatório.')]
    public string $reaTitle;

    #[Validate('required_if:userType,==,colaborador', message: 'A referência é obrigatória.')]
    public string $reference;

    #[Validate('required_if:userType,==,colaborador', message: 'O item é obrigatório.')]
    public string $item;

    #[Validate('max:4096', message: 'Por favor adicione uma mensagem de no máximo 4096 caracteres.')]
    public string $message;

    public bool $showMessage = false;

    public bool $loading = false;

    public bool $feedbackSent = false;

    public array $sheet = [];

    public array $reas = [];

    public Data $data;

    public $timestampSession;

    public ?string $userType = null;

    private array $interestOptions = [
        'algoritmos',
        'decomposição',
        'reconhecimento de padrões',
        'abstração',
    ];

    public string $interestApiSearch = '';

    public $charCount = 0;

    public int $page = 1;

    public int $rating = 0;

    public $comment = '';

    public $selectedReasons = [];

    /**
     * O que o SisREAd usou sobre o usuário na última busca (painel "O que usamos sobre você").
     */
    public array $contexto = [];

    public function updatedMessage($value)
    {
        $this->charCount = strlen($value);
    }

    public function mount()
    {
        //
    }

    public function selectUserType(?string $type = null)
    {
        $this->userType = $type;

        $this->showMessage = false;
    }

    public function prevPage()
    {
        if ($this->page === 1) {
            return;
        }

        $this->page--;
    }

    public function nextPage()
    {
        $this->page++;
    }

    public function irParaPagina(int $pagina)
    {
        $this->page = max(1, $pagina);
    }

    private function temMeta(): bool
    {
        return (bool) auth()->user()?->questionnaire?->dominant;
    }

    public function paginate($data)
    {
        if (! isset($data->data)) {
            return;
        }

        $sortedData = Ranking::ordenar(json_decode($data->data) ?? [], $this->temMeta());

        $items = collect($sortedData);
        $total = $items->count();

        return new LengthAwarePaginator(
            $items->forPage($this->page, 10),
            $total,
            10,
            $this->page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function sendFeedback()
    {
        $this->validate(
            ['message' => 'max:4096|required'],
            ['message.required' => 'O campo de mensagem é obrigatório.',
                'message.max' => 'A mensagem não pode ter mais que 4096 caracteres.']
        );

        $this->showMessage = false;

        Feedback::create([
            'feedback' => $this->message,
        ]);

        $this->reset('message');

        $this->showMessage = true;
    }

    public function setRating(int $rating)
    {
        $this->rating = $rating;

        $this->data->update(['stars' => $rating]);
    }

    public function saveSearchFeedback()
    {
        if (empty($this->selectedReasons) && empty($this->comment)) {
            $this->addError('feedback_vazio', 'Por favor, selecione pelo menos um motivo ou deixe um comentário.');

            return;
        }

        $dadosParaSalvar = [];

        foreach ($this->selectedReasons as $reason) {
            $dadosParaSalvar[$reason] = ['feedback' => $this->comment];
        }

        $this->data->feedbackReasons()->sync($dadosParaSalvar);

        $this->feedbackSent = true;
    }

    public function insert()
    {
        $this->showMessage = false;

        $this->validate();

        Collaborator::create([
            'name' => $this->name,
            'role' => $this->role,
            'institution' => $this->institution,
            'reference' => $this->reference,
            'rea_title' => $this->reaTitle,
            'interest' => $this->sanitizeSearch($this->interest),
            'profile' => $this->sanitizeSearch($this->profile),
            'item' => $this->sanitizeSearch($this->item),
        ]);

        // $getrange = 'Pagina1!A:F';

        // $collaboratorsrange = 'Folha1!A:C';

        // $values = Sheets::spreadsheet(config('google.post_spreadsheet_id'))
        //     ->sheet(config('google.post_sheet_id'))
        //     ->range($getrange)
        //     ->all();

        // $id = count($values);

        // Sheets::spreadsheet(config('google.collaborators_spreadsheet_id'))
        //     ->sheet(config('google.collaborators_sheet_id'))
        //     ->range($collaboratorsrange)
        //     ->append([
        //         [
        //             $this->name,
        //             $this->role,
        //             $this->institution,
        //         ],
        //     ]);

        // Sheets::spreadsheet(config('google.post_spreadsheet_id'))
        //     ->sheet(config('google.post_sheet_id'))
        //     ->range($getrange)
        //     ->append([
        //         [
        //             $id,
        //             $this->reference,
        //             $this->reaTitle,
        //             $this->sanitizeSearch($this->interest),
        //             $this->sanitizeSearch($this->profile),
        //             $this->sanitizeSearch($this->item),
        //         ],
        //     ], 'RAW');

        $this->reset('profile', 'interest', 'name', 'role', 'institution', 'reaTitle', 'reference', 'item');

        $this->showMessage = true;
    }

    /**
     * Contagem por faixa e ocultos para o painel "Como ordenamos".
     */
    public function resumoOrdenacao($data): array
    {
        $comMeta = $this->temMeta();

        return Ranking::contar(json_decode($data->data ?? '[]') ?? [], $comMeta) + [
            'ordem' => Ranking::ordem($comMeta),
            'com_meta' => $comMeta,
        ];
    }

    /**
     * REAs encontrados que não são exibidos, com o motivo de cada um (limitado para não pesar a tela).
     *
     * @return array<int, array{chave: ?string, titulo: string, repositorio: string, motivo: string, resumo: string, corrigiveis: array, corrigidos: array}>
     */
    public function ocultos($data, int $limite = 20): array
    {
        $comMeta = $this->temMeta();
        $ocultos = [];

        foreach (json_decode($data->data ?? '[]') ?? [] as $rea) {
            if (Ranking::visivel($rea, $comMeta)) {
                continue;
            }

            $linhas = ExplanationRenderer::linhas($rea->explicacao ?? null);

            $ocultos[] = [
                'chave' => $rea->chave ?? null,
                'titulo' => $rea->title ?? $rea->titulo ?? 'Sem título',
                'repositorio' => $rea->repositorio ?? '',
                'motivo' => Ranking::motivo($rea, $comMeta),
                'resumo' => ExplanationRenderer::resumo($rea->explicacao ?? null),
                'corrigiveis' => array_column(array_filter($linhas, fn ($l) => $l['corrigivel']), 'criterio'),
                'corrigidos' => array_column(array_filter($linhas, fn ($l) => $l['corrigido']), 'criterio'),
            ];

            if (count($ocultos) >= $limite) {
                break;
            }
        }

        return $ocultos;
    }

    /**
     * Situação de cada repositório na busca (search_metrics): quantos itens vieram e se houve falha.
     */
    public function statusRepositorios($data): array
    {
        $metricas = DB::table('search_metrics')
            ->where('searched_at', $data->getRawOriginal('searched_at'))
            ->get()
            ->keyBy('repository');

        $status = [];

        foreach (['Aquarela', 'MecRed' => 'MEC RED', 'Eduplay'] as $chave => $nome) {
            $chave = is_int($chave) ? $nome : $chave;
            $m = $metricas->get($chave);

            $status[$nome] = match (true) {
                $m === null => ['situacao' => 'aguardando', 'itens' => 0],
                $m->timeouts_errors > 0 && $m->items_returned == 0 => ['situacao' => 'falhou', 'itens' => 0],
                $m->timeouts_errors > 0 => ['situacao' => 'parcial', 'itens' => (int) $m->items_returned],
                default => ['situacao' => 'ok', 'itens' => (int) $m->items_returned],
            };
        }

        return $status;
    }

    #[Renderless]
    public function registrarExplicacao(string $acao, ?string $repositorio = null, ?string $titulo = null, ?string $faixa = null): void
    {
        if (! in_array($acao, ExplanationEvent::ACOES, true)) {
            return;
        }

        ExplanationEvent::create([
            'searched_at' => $this->timestampSession,
            'user_id' => auth()->id(),
            'acao' => $acao,
            'repositorio' => $repositorio ? mb_substr($repositorio, 0, 50) : null,
            'titulo' => $titulo ? mb_substr($titulo, 0, 255) : null,
            'faixa' => $faixa ? mb_substr($faixa, 0, 20) : null,
        ]);
    }

    /**
     * Correções só depois que todos os repositórios responderam: os jobs regravam `data.data` inteiro
     * sem lock (problema #3), e uma correção feita no meio se perderia.
     */
    public function podeCorrigir($data): bool
    {
        if (! $data || empty($data->data)) {
            return false;
        }

        foreach ($this->statusRepositorios($data) as $status) {
            if ($status['situacao'] === 'aguardando') {
                return false;
            }
        }

        return true;
    }

    public function corrigirNivel(string $chave, string $nivel): void
    {
        $this->corrigirItem('nivel', $chave, fn (array $rea) => UserCorrections::corrigirNivel($rea, $nivel));
    }

    public function corrigirMeta(string $chave, string $meta): void
    {
        $this->corrigirItem('meta', $chave, fn (array $rea) => UserCorrections::corrigirMeta($rea, $meta));
    }

    public function desfazerCorrecao(string $chave, string $criterio): void
    {
        if (! in_array($criterio, ['nivel', 'meta'], true)) {
            return;
        }

        $this->corrigirItem($criterio, $chave, fn (array $rea) => UserCorrections::desfazer($rea, $criterio), 'desfazer');
    }

    /**
     * Tipos que o usuário pode marcar como preferidos: os preferidos do sistema, os já escolhidos e os
     * tipos que aparecem nos REAs desta busca (só onde o tipo é comparado com os preferidos).
     *
     * `contagem`: quantos REAs comparáveis da busca têm cada tipo (o efeito de marcar aquele tipo).
     *
     * @return array{opcoes: array<int, string>, atuais: array<int, string>, originais: array<int, string>, editados: bool, contagem: array<string, int>}
     */
    public function tiposPreferidos($data): array
    {
        $opcoes = [];
        $contagem = [];
        $atuais = null;
        $originais = null;
        $editados = false;

        foreach (json_decode($data->data ?? '[]', true) ?? [] as $rea) {
            $tipo = $rea['explicacao']['criterios']['tipo'] ?? null;

            if (($tipo['fonte'] ?? null) !== 'colaboradores') {
                continue;
            }

            // Todos os REAs da busca compartilham a mesma lista de tipos preferidos.
            $atuais ??= $tipo['esperado'] ?? [];
            $originais ??= $tipo['esperado_original'] ?? $tipo['esperado'] ?? [];
            $editados = $editados || isset($tipo['esperado_original']);

            $opcoes[] = $tipo['valor'] ?? '';

            if (($tipo['valor'] ?? '') !== '') {
                $contagem[$tipo['valor']] = ($contagem[$tipo['valor']] ?? 0) + 1;
            }
        }

        $opcoes = array_values(array_unique(array_filter(array_merge($originais ?? [], $atuais ?? [], $opcoes))));
        sort($opcoes);

        return [
            'opcoes' => $opcoes,
            'atuais' => $atuais ?? [],
            'originais' => $originais ?? [],
            'editados' => $editados,
            'contagem' => $contagem,
        ];
    }

    public function redefinirTipos(array $tipos): void
    {
        $this->resetErrorBag('correcao');
        $data = $this->dadosDaBusca();

        if (! $this->podeCorrigir($data)) {
            $this->addError('correcao', 'Correções ficam disponíveis quando todos os repositórios responderem.');

            return;
        }

        $preferidos = $this->tiposPreferidos($data);
        $tipos = RuleClassifier::normalizarTipos(array_filter($tipos, 'is_string'));

        if (! $preferidos['opcoes'] && ! $preferidos['originais']) {
            $this->addError('correcao', 'Nenhum REA desta busca é comparado com os tipos preferidos.');

            return;
        }

        if (array_diff($tipos, $preferidos['opcoes'])) {
            $this->addError('correcao', 'Escolha os tipos entre as opções oferecidas.');

            return;
        }

        $restaura = empty(array_diff($tipos, $preferidos['originais'])) && empty(array_diff($preferidos['originais'], $tipos));

        $this->aplicarEmTodos(
            $data,
            fn (array $rea) => UserCorrections::redefinirTipos($rea, $tipos),
            ['acao' => $restaura ? 'desfazer' : 'corrigir', 'alvo' => 'tipos',
                'valor_anterior' => $preferidos['atuais'], 'valor_novo' => $tipos]
        );

        if ($this->contexto) {
            $this->contexto['tipos_usuario'] = $restaura ? null : $tipos;
        }
    }

    /**
     * Desfaz todas as correções da busca: nível, meta e tipos preferidos.
     */
    public function desfazerTodas(): void
    {
        $this->resetErrorBag('correcao');
        $data = $this->dadosDaBusca();

        if (! $this->podeCorrigir($data)) {
            return;
        }

        $this->aplicarEmTodos(
            $data,
            fn (array $rea) => UserCorrections::desfazer(UserCorrections::desfazer(UserCorrections::desfazer($rea, 'nivel'), 'meta'), 'tipo'),
            ['acao' => 'desfazer', 'alvo' => 'todas']
        );

        if ($this->contexto) {
            $this->contexto['tipos_usuario'] = null;
        }
    }

    private function dadosDaBusca(): ?Data
    {
        return $this->timestampSession ? Data::query()->where('searched_at', $this->timestampSession)->first() : null;
    }

    /**
     * Aplica a correção em todas as ocorrências do REA (a mesma chave pode vir em mais de uma página) e registra.
     */
    private function corrigirItem(string $alvo, string $chave, callable $correcao, string $acao = 'corrigir'): void
    {
        $this->resetErrorBag('correcao');
        $data = $this->dadosDaBusca();

        if (! $this->podeCorrigir($data)) {
            $this->addError('correcao', 'Correções ficam disponíveis quando todos os repositórios responderem.');

            return;
        }

        $reas = json_decode($data->data, true) ?? [];
        $antes = null;
        $depois = null;

        try {
            foreach ($reas as $i => $rea) {
                if ($chave === '' || ($rea['chave'] ?? null) !== $chave) {
                    continue;
                }

                $reas[$i] = $correcao($rea);
                $antes ??= $rea;
                $depois ??= $reas[$i];
            }
        } catch (InvalidArgumentException) {
            $this->addError('correcao', 'Esta correção não é válida para este REA.');

            return;
        }

        if ($antes === null || $antes === $depois) {
            return;
        }

        DB::transaction(function () use ($data, $reas, $antes, $depois, $alvo, $acao, $chave) {
            $data->update(['data' => json_encode($reas)]);

            $this->registrarCorrecao([
                'acao' => $acao,
                'alvo' => $alvo,
                'chave_rea' => $chave,
                'repositorio' => $antes['repositorio'] ?? null,
                'titulo' => isset($antes['title']) ? mb_substr($antes['title'], 0, 255) : null,
                'valor_anterior' => $antes['explicacao']['criterios'][$alvo] ?? null,
                'valor_novo' => $depois['explicacao']['criterios'][$alvo] ?? null,
                'faixa_anterior' => $antes['recommended'] ?? null,
                'faixa_nova' => $depois['recommended'] ?? null,
                'itens_afetados' => ($antes['recommended'] ?? null) !== ($depois['recommended'] ?? null) ? 1 : 0,
            ]);
        });
    }

    /**
     * Aplica a correção a todos os REAs da busca; `itens_afetados` conta os que mudaram de faixa.
     */
    private function aplicarEmTodos(Data $data, callable $correcao, array $registro): void
    {
        $reas = json_decode($data->data, true) ?? [];
        $mudou = false;
        $mudaramFaixa = 0;

        foreach ($reas as $i => $rea) {
            $novo = $correcao($rea);
            $mudou = $mudou || $novo !== $rea;
            $mudaramFaixa += ($novo['recommended'] ?? null) !== ($rea['recommended'] ?? null) ? 1 : 0;
            $reas[$i] = $novo;
        }

        if (! $mudou) {
            return;
        }

        DB::transaction(function () use ($data, $reas, $registro, $mudaramFaixa) {
            $data->update(['data' => json_encode($reas)]);

            $this->registrarCorrecao($registro + ['itens_afetados' => $mudaramFaixa]);
        });
    }

    private function registrarCorrecao(array $campos): void
    {
        Correction::create($campos + [
            'searched_at' => $this->timestampSession,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Interesses que o SisREAd sabe buscar: os fixos mais os cadastrados por colaboradores.
     * É calculado a cada requisição porque propriedades privadas não sobrevivem entre elas no Livewire.
     */
    public function opcoesInteresse(): array
    {
        $opcoes = [];

        // Os fixos vêm primeiro, então a grafia com acento prevalece sobre a versão sanitizada do banco.
        foreach (array_merge($this->interestOptions, Collaborator::query()->pluck('interest')->filter()->all()) as $opcao) {
            $chave = RuleClassifier::normalizar($opcao);

            if ($chave !== '' && ! isset($opcoes[$chave])) {
                $opcoes[$chave] = $opcao;
            }
        }

        return array_values($opcoes);
    }

    public function search()
    {
        $this->validate();

        // Sem termo conhecido a busca sairia vazia e a tela não mostraria nada (problema #14).
        $this->findAdequateTerm();

        if ($this->interestApiSearch === '') {
            $this->addError('interest', 'Não sabemos buscar por “'.$this->interest.'”. Interesses disponíveis: '
                .implode(', ', $this->opcoesInteresse()).'.');

            return;
        }

        $this->timestampSession = now()->setTimezone('UTC');
        $this->page = 1;

        Searches::create([
            'interest' => $this->sanitizeSearch($this->interest),
            'profile' => $this->sanitizeSearch($this->profile),
        ]);

        $getrange = 'Pagina1!A:F';

        $collaborators = collect(Collaborator::lazyById(100, $column = 'id'))
            ->map(function ($collaborator) {
                return [
                    $collaborator->id,
                    $collaborator->reference,
                    $collaborator->rea_title,
                    $collaborator->interest,
                    $collaborator->profile,
                    $collaborator->item,
                ];
            });

        $values = $collaborators->all();

        $this->sheet = array_filter(
            $values,
            fn ($rea) => $rea[3] === $this->sanitizeSearch($this->interest) && $rea[4] === $this->sanitizeSearch($this->profile)
        );

        $this->findInApi();

        $this->reset('profile', 'interest');
    }

    private function sanitizeSearch(string $search)
    {
        $search = mb_strtolower($search, 'UTF-8');
        $search = preg_replace('/[áàâã]/u', 'a', $search);
        $search = preg_replace('/[éèê]/u', 'e', $search);
        $search = preg_replace('/[íì]/u', 'i', $search);
        $search = preg_replace('/[óòôõ]/u', 'o', $search);
        $search = preg_replace('/[úùû]/u', 'u', $search);
        $search = preg_replace('/ç/u', 'c', $search);

        return $search;
    }

    private function findInApi()
    {
        $this->loading = true;

        // Tipos de colaboradores com o mesmo tema e perfil, e tipos de todos os colaboradores.
        $tiposBusca = RuleClassifier::normalizarTipos(array_column($this->sheet, 5));
        $tiposGerais = array_values(array_diff(
            RuleClassifier::normalizarTipos(Collaborator::query()->pluck('item')->all()),
            $tiposBusca
        ));
        $types = array_merge($tiposBusca, $tiposGerais);

        $questionnaire = auth()->user()?->questionnaire;

        $this->contexto = [
            'perfil' => $this->profile,
            'interesse' => $this->interest,
            'termo_api' => $this->interestApiSearch,
            'tipos_busca' => $tiposBusca,
            'tipos_gerais' => $tiposGerais,
            'meta' => $questionnaire?->dominant ? [
                'dominante' => $questionnaire->dominant,
                'ma' => round((float) $questionnaire->ma, 2),
                'mpa' => round((float) $questionnaire->mpa, 2),
                'mpe' => round((float) $questionnaire->mpe, 2),
            ] : null,
        ];

        $this->data = Data::create(['searched_at' => $this->timestampSession]);

        ProcessAquarela::dispatch($this->interestApiSearch, $types, $this->profile, $this->timestampSession, auth()->user()?->questionnaire?->dominant);

        ProcessMecRed::dispatch($this->interestApiSearch, $types, $this->profile, $this->interest, $this->timestampSession, auth()->user()?->questionnaire?->dominant);

        ProcessEduplay::dispatch($this->interestApiSearch, $this->profile, $this->timestampSession, auth()->user()?->questionnaire?->dominant, $types);

        $this->loading = false;
    }

    private function findAdequateTerm()
    {
        foreach ($this->opcoesInteresse() as $option) {
            if ($this->sanitizeSearch($option) === $this->sanitizeSearch($this->interest)) {
                $this->interestApiSearch = $option;
            }
        }
    }

    public function render()
    {
        return view('livewire.find-r-e-a');
    }
}
