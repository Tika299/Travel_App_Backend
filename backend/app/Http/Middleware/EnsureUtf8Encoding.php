<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUtf8Encoding
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Đảm bảo response có charset UTF-8
        if ($response->headers->get('Content-Type')) {
            $contentType = $response->headers->get('Content-Type');
            if (strpos($contentType, 'application/json') !== false && strpos($contentType, 'charset=UTF-8') === false) {
                $response->headers->set('Content-Type', $contentType . '; charset=UTF-8');
            }
        } else {
            $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        }

        return $response;
    }
}

