<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureSalaryTextEncoding
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }
        $errors = [];
        $clean = function (array $input, string $prefix = '') use (&$clean, &$errors): array {
            $safe = [];
            foreach ($input as $key => $value) {
                if (! mb_check_encoding((string) $key, 'UTF-8')) {
                    $errors['input'] = 'Field names must contain valid UTF-8 text.';

                    continue;
                }
                $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                if (is_array($value)) {
                    $value = $clean($value, $path);
                } elseif (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                    $errors[$path] = 'This field must contain valid UTF-8 text.';
                    $value = ''; // Rejected bytes must not corrupt JSON flash/session data.
                }
                $safe[$key] = $value;
            }

            return $safe;
        };
        $body = $clean($request->input());
        $query = $clean($request->query());
        if ($errors !== []) {
            $request->replace($body);
            $request->query->replace($query);
            throw ValidationException::withMessages($errors);
        }

        return $next($request);
    }
}
