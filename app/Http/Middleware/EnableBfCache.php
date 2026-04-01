<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnableBfCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->remove('Pragma');
            $response->headers->remove('Expires');
            $response->headers->set('Cache-Control', 'no-cache, private');
        }

        return $response;
    }
}
