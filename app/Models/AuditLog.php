<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'created_at'])]
class AuditLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_trail';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The user who performed the audited action, if known.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * What this entry is about, in words staff recognise: "Job Order
     * JO-2026-0230", "Transaction for JO-2026-0230", "User Grace Bautista".
     * Null for entries about no record (auth events, report exports).
     *
     * ponytail: one or two small lookups per row; eager-load a morphTo if the
     * 1000-row PDF export ever gets slow.
     */
    public function recordLabel(): ?string
    {
        if ($this->auditable_type === null) {
            return null;
        }

        $kind = Str::headline(class_basename($this->auditable_type));

        if ($this->auditable_id === null) {
            return $kind;
        }

        $subject = $this->subjectName() ?? '#'.$this->auditable_id;

        return in_array($this->auditable_type, [Transaction::class, ProductionLog::class, DesignFile::class, RevisionLog::class, AccountsReceivable::class], true)
            ? "{$kind} for {$subject}"
            : "{$kind} {$subject}";
    }

    /**
     * The audited record's own name, or its job order's number for records
     * that only exist as part of a job order.
     */
    private function subjectName(): ?string
    {
        $id = $this->auditable_id;
        $jobOrderNumber = fn (string $model): ?string => JobOrder::query()
            ->whereKey($model::query()->whereKey($id)->value('job_order_id'))
            ->value('number');

        $name = match ($this->auditable_type) {
            JobOrder::class => JobOrder::query()->whereKey($id)->value('number'),
            Transaction::class, ProductionLog::class, DesignFile::class, RevisionLog::class, AccountsReceivable::class => $jobOrderNumber($this->auditable_type),
            User::class => User::withTrashed()->whereKey($id)->value('name'),
            Customer::class => Customer::query()->whereKey($id)->value('name'),
            PricingEntry::class => PricingEntry::query()->whereKey($id)->value('name'),
            SpecificationOption::class => SpecificationOption::query()->whereKey($id)->value('label'),
            SystemConfiguration::class => SystemConfiguration::query()->whereKey($id)->value('key'),
            QueueEntry::class => QueueEntry::query()->find($id)?->paddedNumber(),
            Expense::class => Expense::query()->whereKey($id)->value('description'),
            default => null,
        };

        return is_string($name) && $name !== '' ? $name : null;
    }
}
