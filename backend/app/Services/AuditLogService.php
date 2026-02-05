<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Log an action
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
        ?int $userId = null
    ): AuditLog {
        $request = $request ?? request();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => self::sanitizeForLog($oldValues),
            'new_values' => self::sanitizeForLog($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 500),
        ]);
    }

    /**
     * Remove sensitive fields from data before storing in audit log.
     */
    private static function sanitizeForLog(?array $data): ?array
    {
        if ($data === null || $data === []) {
            return $data;
        }

        $sensitive = ['password', 'password_confirmation', 'token', '_token', 'api_token', 'remember_token'];
        foreach ($sensitive as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    /**
     * Log user login
     */
    public static function logLogin(User $user, Request $request): void
    {
        self::log('login', 'user', $user->id, null, ['email' => $user->email], $request, $user->id);
    }

    /**
     * Log user logout
     */
    public static function logLogout(User $user, Request $request): void
    {
        self::log('logout', 'user', $user->id, null, null, $request, $user->id);
    }

    /**
     * Log entity creation
     */
    public static function logCreate(string $entityType, int $entityId, array $data, Request $request = null): void
    {
        self::log('created', $entityType, $entityId, null, $data, $request);
    }

    /**
     * Log entity update
     */
    public static function logUpdate(string $entityType, int $entityId, array $oldData, array $newData, Request $request = null): void
    {
        self::log('updated', $entityType, $entityId, $oldData, $newData, $request);
    }

    /**
     * Log entity deletion
     */
    public static function logDelete(string $entityType, int $entityId, array $data, Request $request = null): void
    {
        self::log('deleted', $entityType, $entityId, $data, null, $request);
    }
}
