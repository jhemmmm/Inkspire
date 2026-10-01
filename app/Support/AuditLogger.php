<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
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

    /**
     * Record a report export (D-02, D-07). An export mutates no model, so
     * AuditObserver never fires for it -- this is the phase's one
     * deliberate non-model-driven audit write, following
     * recordAuthEvent()'s exact direct-AuditLog::create() shape. Must be
     * called before any byte of the export file streams to the client.
     *
     * `$from`/`$to` are nullable so an unranged export (e.g. the Job
     * Orders/Audit Trail list exports, which may have no date filter
     * applied) can still be audited -- existing Reports workspace callers
     * keep resolving a non-null month-to-date default and so keep writing
     * byte-for-byte identical `new_values`.
     */
    public static function recordReportExport(User $user, string $reportKey, string $format, ?CarbonInterface $from, ?CarbonInterface $to): void
    {
        $newValues = ['report' => $reportKey, 'format' => $format];

        if ($from !== null) {
            $newValues['from'] = $from->toDateString();
        }

        if ($to !== null) {
            $newValues['to'] = $to->toDateString();
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'report_exported',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
