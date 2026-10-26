<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PreventStatutoryInputFlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Statutory forms intentionally restore saved values only. Remove the
        // framework's rejected input so other workflows cannot consume shared keys.
        if ($response instanceof RedirectResponse && $response->exception instanceof ValidationException) {
            $request->session()->forget('_old_input');
        }

        return $response;
    }
}
