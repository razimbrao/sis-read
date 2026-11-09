<?php

if (!function_exists('comParams')) {
    /**
     * Acrescenta a um link interno os query params da página atual, para que eles acompanhem a navegação.
     * Os params do próprio link têm prioridade. `secao` e `page` dizem respeito só à página em que estão.
     *
     * Em POST (Livewire, logout) a query da requisição não é a da página, então ela vem do Referer.
     */
    function comParams(string $url): string
    {
        $request = request();

        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            $atuais = $request->query();
        } else {
            $origem = parse_url((string) $request->headers->get('referer')) ?: [];
            $atuais = [];

            if (($origem['host'] ?? null) === $request->getHost()) {
                parse_str($origem['query'] ?? '', $atuais);
            }
        }

        [$caminho, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $doLink);

        $params = array_replace(\Illuminate\Support\Arr::except($atuais, ['secao', 'page']), $doLink);

        return $params ? $caminho.'?'.\Illuminate\Support\Arr::query($params) : $url;
    }
}

if (!function_exists('mecRedEtapas')) {
    /**
     * IDs de `educational_stages` do MEC RED para o perfil (vazio = sem filtro de nível).
     */
    function mecRedEtapas($profile): array
    {
        return match ($profile) {
            'Educação infantil'  => [1],
            'Ensino fundamental' => [2, 3],
            'Ensino médio'       => [4],
            'Ensino superior'    => [5],
            default              => [],
        };
    }
}

if (!function_exists('mecRedTiposObjeto')) {
    /**
     * IDs de `object_type` do MEC RED associados à meta (vazio = sem filtro de tipo).
     */
    function mecRedTiposObjeto($meta): array
    {
        return match ($meta) {
            'ma'    => [6, 8, 22, 18, 21, 3, 20, 4, 5, 1],
            'mpa'   => [17, 13, 3, 19, 20, 8, 18],
            'mpe'   => [17, 22, 6, 18, 13],
            default => [],
        };
    }
}

if (!function_exists('getMecRedURL')) {
    function getMecRedURL($query, $offset, $profile, $meta = null)
    {
        $educational_stages = implode('', array_map(fn ($id) => "&educational_stages={$id}", mecRedEtapas($profile)));
        $object_type = implode('', array_map(fn ($id) => "&object_type={$id}", mecRedTiposObjeto($meta)));

        return "https://api.mecred.c3sl.ufpr.br/public/elastic/search?indexes=resources&query={$query}{$educational_stages}{$object_type}&state=accepted&sortBy=score&limit=10&offset={$offset}&filters=%7B%22pesquisa%22%3A%22{$query}%22%2C%22formato%22%3A%5B%5D%2C%22nivel%22%3A%5B%5D%2C%22idioma%22%3A%5B%5D%2C%22materias%22%3A%5B%5D%2C%22entidade%22%3A%22resources%22%2C%22categoria%22%3A%22created_at%22%7D";
    }
}
