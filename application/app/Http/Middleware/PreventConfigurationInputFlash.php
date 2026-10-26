<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PreventConfigurationInputFlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response instanceof RedirectResponse && $response->exception instanceof ValidationException) {
            $request->session()->forget('_old_input');
        }

        return $response;
    }
}
