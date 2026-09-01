<?php

namespace App\Models;

use App\Enums\QueueStatus;
use App\Observers\AuditObserver;
use Database\Factories\QueueEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $customer_id
 * @property Carbon $queue_date
 * @property int $queue_number
 * @property QueueStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['customer_id', 'queue_date', 'queue_number', 'status'])]
#[ObservedBy(AuditObserver::class)]
class QueueEntry extends Model
{
    /** @use HasFactory<QueueEntryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QueueStatus::class,
            'queue_date' => 'date',
        ];
    }

    /**
     * The customer this visit belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The job orders created during this visit.
     *
     * @return HasMany<JobOrder, $this>
     */
    public function jobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class);
    }

    /**
     * The shop's current business day (D-16), scoped narrowly to this one
     * call site.
     *
     * Single source of truth for "today" as far as the daily queue-number
     * reset is concerned — do not re-derive this conversion elsewhere.
     * config('app.timezone') stays UTC project-wide.
     */
    public static function currentBusinessDate(): string
    {
        return now()->timezone('Asia/Manila')->toDateString();
    }

    /**
     * Compute the next sequential queue number for the given business date,
     * under a database lock so concurrent requests can never collide (D-06,
     * D-16, D-17; RESEARCH.md Pattern 1).
     *
     * Uses whereDate() rather than a plain where() because the model's
     * `queue_date` cast serializes via fromDateTime() (format `Y-m-d H:i:s`)
     * on write — a raw string-equality where() against a bare `Y-m-d`
     * business-date string silently never matches on SQLite, which (unlike
     * MySQL's native DATE column) does not truncate the stored value.
     */
    public static function nextForBusinessDay(string $businessDate): int
    {
        return DB::transaction(fn (): int => (static::query()
            ->whereDate('queue_date', $businessDate)
            ->lockForUpdate()
            ->max('queue_number') ?? 0) + 1);
    }
}
