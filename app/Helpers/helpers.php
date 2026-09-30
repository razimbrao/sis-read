<?php

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
