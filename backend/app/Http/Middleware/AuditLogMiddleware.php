<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AuditLog;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $action = null): Response
    {
        $response = $next($request);

        // Only log successful requests (2xx status codes)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->logAction($request, $response, $action);
        }

        return $response;
    }

    /**
     * Log the action
     */
    private function logAction(Request $request, Response $response, $action = null)
    {
        // Determine action from route or parameter
        if (!$action) {
            $action = $this->determineAction($request);
        }

        // Skip logging for certain routes
        if ($this->shouldSkipLogging($request)) {
            return;
        }

        // Extract entity information
        $entityType = $this->extractEntityType($request);
        $entityId = $this->extractEntityId($request, $response);

        // Get old and new values
        $oldValues = $this->getOldValues($request);
        $newValues = $this->getNewValues($request, $response);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Determine action from request
     */
    private function determineAction(Request $request): string
    {
        $method = $request->method();
        $route = $request->route();

        if ($method === 'POST') {
            // Check if it's an update or create
            if ($route && str_contains($route->getName() ?? '', 'update')) {
                return 'updated';
            }
            if ($route && str_contains($route->getName() ?? '', 'approve')) {
                return 'approved';
            }
            if ($route && str_contains($route->getName() ?? '', 'reject')) {
                return 'rejected';
            }
            if ($route && str_contains($route->getName() ?? '', 'submit')) {
                return 'submitted';
            }
            return 'created';
        } elseif ($method === 'PUT' || $method === 'PATCH') {
            return 'updated';
        } elseif ($method === 'DELETE') {
            return 'deleted';
        } elseif ($method === 'GET') {
            return 'viewed';
        }

        return 'accessed';
    }

    /**
     * Extract entity type from request
     */
    private function extractEntityType(Request $request): ?string
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        $routeName = $route->getName() ?? '';
        
        // Extract entity type from route name (e.g., 'users.index' -> 'user')
        if (preg_match('/^(\w+)\./', $routeName, $matches)) {
            $entity = $matches[1];
            // Convert plural to singular
            if (str_ends_with($entity, 'ies')) {
                return substr($entity, 0, -3) . 'y';
            } elseif (str_ends_with($entity, 'es')) {
                return substr($entity, 0, -2);
            } elseif (str_ends_with($entity, 's')) {
                return substr($entity, 0, -1);
            }
            return $entity;
        }

        return null;
    }

    /**
     * Extract entity ID from request or response
     */
    private function extractEntityId(Request $request, Response $response): ?int
    {
        // Try to get from route parameters
        $route = $request->route();
        if ($route) {
            $params = $route->parameters();
            foreach (['id', 'userId', 'teamId', 'projectId', 'reportId'] as $key) {
                if (isset($params[$key])) {
                    return (int) $params[$key];
                }
            }
        }

        // Try to get from response data
        $content = $response->getContent();
        $data = json_decode($content, true);
        if (isset($data['data']['id'])) {
            return (int) $data['data']['id'];
        }

        return null;
    }

    /**
     * Get old values (for updates)
     */
    private function getOldValues(Request $request): ?array
    {
        // For updates, we would need to fetch the old record
        // This is a simplified version
        return null;
    }

    /**
     * Get new values from request
     */
    private function getNewValues(Request $request, Response $response): ?array
    {
        // Get from request (excluding sensitive fields)
        $data = $request->except(['password', 'password_confirmation', 'token', '_token']);
        
        // Remove empty arrays
        if (empty($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Check if logging should be skipped
     */
    private function shouldSkipLogging(Request $request): bool
    {
        $route = $request->route();
        if (!$route) {
            return true;
        }

        $routeName = $route->getName() ?? '';
        
        // Skip logging for certain routes
        $skipRoutes = [
            'login',
            'logout',
            'me',
            'dashboard',
            'index', // List views
        ];

        foreach ($skipRoutes as $skip) {
            if (str_contains($routeName, $skip)) {
                return true;
            }
        }

        return false;
    }
}
