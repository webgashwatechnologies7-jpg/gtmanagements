<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Fields that should be sanitized (strip HTML tags) for XSS prevention.
     * Only string inputs are sanitized.
     */
    protected array $sanitizeKeys = [
        'name', 'description', 'title', 'notes', 'comment', 'reason',
        'work_summary', 'blockers', 'status_update', 'rejection_reason',
        'search', 'email', 'phone', 'employee_id',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        foreach ($input as $key => $value) {
            if (! in_array($key, $this->sanitizeKeys, true)) {
                continue;
            }
            if (is_string($value)) {
                $input[$key] = strip_tags($value);
            }
            if (is_array($value)) {
                $input[$key] = $this->sanitizeArray($value);
            }
        }

        $request->merge($input);

        return $next($request);
    }

    private function sanitizeArray(array $arr): array
    {
        foreach ($arr as $k => $v) {
            if (is_string($v)) {
                $arr[$k] = strip_tags($v);
            } elseif (is_array($v)) {
                $arr[$k] = $this->sanitizeArray($v);
            }
        }
        return $arr;
    }
}
