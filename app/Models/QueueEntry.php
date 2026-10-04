<?php

namespace App\Models;

use App\Enums\QueueStatus;
use App\Observers\AuditObserver;
use App\Support\BusinessTime;
use Database\Factories\QueueEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $customer_id
 * @property string|null $contact_email
 * @property Carbon $queue_date
 * @property string $queue_prefix
 * @property int $queue_number
 * @property QueueStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['customer_id', 'contact_email', 'queue_date', 'queue_prefix', 'queue_number', 'status'])]
#[ObservedBy(AuditObserver::class)]
class QueueEntry extends Model
{
    /** @use HasFactory<QueueEntryFactory> */
    use HasFactory;

    /** The rush lane's ticket prefix: R-001, R-002, ... */
    public const string RUSH_PREFIX = 'R';

    /** The regular lane's ticket prefix: A-001, A-002, ... */
    public const string REGULAR_PREFIX = 'A';

    /**
     * The online lane's ticket prefix: O-001, O-002, ... Remote orders,
     * emailed to an artist or placed on the website, where nobody is
     * standing in the shop. Never shown on the queue displays.
     */
    public const string ONLINE_PREFIX = 'O';

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
     * Where this visit's emails go: the address a website order was
     * confirmed from, otherwise the customer's own.
     */
    public function contactEmail(): string
    {
        return $this->contact_email ?? $this->customer->email;
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
    /**
     * The ticket as the shop writes it: R-001 for a rush visit, A-001 for a
     * regular one.
     *
     * Three digits because the numbering resets each business day and the
     * shop has never come close to 1000 visits in one -- past that it simply
     * grows, rather than truncating.
     */
    public function paddedNumber(): string
    {
        return $this->queue_prefix.'-'.str_pad((string) $this->queue_number, 3, '0', STR_PAD_LEFT);
    }

    public static function currentBusinessDate(): string
    {
        return BusinessTime::now()->toDateString();
    }

    /**
     * Today's visits by people standing in the shop, in the order they are
     * called: the rush lane first, then by number. The online lane is left
     * out, as it is on every queue display.
     *
     * whereDate(), not where(), for the reason nextForBusinessDay() gives.
     *
     * @param  Builder<QueueEntry>  $query
     */
    public function scopeTodaysFloorQueue(Builder $query): void
    {
        $query->whereDate('queue_date', self::currentBusinessDate())
            ->where('queue_prefix', '!=', self::ONLINE_PREFIX)
            ->orderByRaw('CASE queue_prefix WHEN ? THEN 0 ELSE 1 END', [self::RUSH_PREFIX])
            ->orderBy('queue_number');
    }

    /**
     * Compute the next sequential queue number for the given business date
     * and lane, under a database lock so concurrent requests can never
     * collide (D-06, D-16, D-17; RESEARCH.md Pattern 1).
     *
     * Each lane counts independently, so the rush queue reaching R-004 does
     * not push the next regular ticket past A-002. The lock and the max()
     * are therefore both scoped to one prefix.
     *
     * Uses whereDate() rather than a plain where() because the model's
     * `queue_date` cast serializes via fromDateTime() (format `Y-m-d H:i:s`)
     * on write — a raw string-equality where() against a bare `Y-m-d`
     * business-date string silently never matches on SQLite, which (unlike
     * MySQL's native DATE column) does not truncate the stored value.
     */
    public static function nextForBusinessDay(string $businessDate, string $prefix = self::REGULAR_PREFIX): int
    {
        return DB::transaction(fn (): int => (static::query()
            ->whereDate('queue_date', $businessDate)
            ->where('queue_prefix', $prefix)
            ->lockForUpdate()
            ->max('queue_number') ?? 0) + 1);
    }
}
