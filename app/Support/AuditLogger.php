<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes append-only rows to the audit_trail table.
 *
 * Every method here must call AuditLog::create() only. No update()/delete()
 * code path against AuditLog may ever be added anywhere in this codebase —
 * see AUDIT-02 / D-04.
 */
final class AuditLogger
{
    /**
     * Record an Eloquent model mutation (create/update/delete).
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function recordMutation(string $action, Model $model, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Record an authentication event (login/logout/failed_login/lockout).
     */
    public static function recordAuthEvent(?User $user, string $action, ?Request $request = null): void
    {
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $user ? User::class : null,
            'auditable_id' => $user?->id,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => ($request ?? request())->ip(),
            'created_at' => now(),
        ]);
    }
}
