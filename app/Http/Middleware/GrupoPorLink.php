<?php

namespace App\Http\Middleware;

use App\Experimento\Experimento;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica o grupo do experimento vindo do link do pesquisador (/?grupo=<código>).
 *
 * O parâmetro acompanha a navegação (comParams), então o mesmo código volta em toda página.
 * Só um código novo na sessão conta como link: senão, entrar numa conta que já tem grupo o trocaria.
 */
class GrupoPorLink
{
    private const SESSAO_CODIGO = 'experimento.codigo_link';

    public function __construct(private Experimento $experimento)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $codigo = $request->query(config('experimento.parametro_url', 'grupo'));

        if (is_string($codigo) && $codigo !== '' && $codigo !== $request->session()->get(self::SESSAO_CODIGO)) {
            if ($this->experimento->definirPorCodigo($codigo) !== null) {
                $request->session()->put(self::SESSAO_CODIGO, $codigo);
            }
        }

        return $next($request);
    }
}
