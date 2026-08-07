<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRagTokenIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.rag.internal_token');
        $providedToken = $request->input('token');

        if (! is_string($expectedToken) || $expectedToken === '' || ! is_string($providedToken) || ! hash_equals($expectedToken, $providedToken)) {
            return new JsonResponse(['detail' => 'Неверный внутренний токен RAG.'], 401);
        }

        return $next($request);
    }
}
