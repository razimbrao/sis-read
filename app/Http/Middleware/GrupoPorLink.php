<?php

namespace App\Http\Middleware;

use App\Experimento\Experimento;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica o grupo do experimento vindo do link do pesquisador (/?grupo=<código>).
 */
class GrupoPorLink
{
    public function __construct(private Experimento $experimento)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $codigo = $request->query(config('experimento.parametro_url', 'grupo'));

        if (is_string($codigo) && $codigo !== '') {
            $this->experimento->definirPorCodigo($codigo);
        }

        return $next($request);
    }
}
