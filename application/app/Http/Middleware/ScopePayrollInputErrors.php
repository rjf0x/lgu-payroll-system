<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ScopePayrollInputErrors
{
    public const CONTEXT_KEY = '_payroll_inputs_context';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Laravel's routing pipeline converts validation exceptions into responses
        // before outer middleware resumes. JSON errors do not flash form input.
        if ($response instanceof RedirectResponse && $response->exception instanceof ValidationException) {
            // Use bound route models, never a client-supplied form identity.
            $request->session()->flash(self::CONTEXT_KEY, [
                'batch_id' => (string) $request->route('batch')->id,
                'entry_id' => (string) $request->route('entry')->id,
                'employee_id' => (string) $request->route('entry')->employee_id,
            ]);
        }

        return $response;
    }
}
