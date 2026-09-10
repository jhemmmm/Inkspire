<?php

namespace App\Models;

use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Observers\AuditObserver;
use Database\Factories\JobOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $number
 * @property string $tracking_token
 * @property int $queue_entry_id
 * @property string $description
 * @property string|null $print_size
 * @property string|null $material
 * @property int|null $quantity
 * @property Carbon|null $deadline
 * @property string|null $client_notes
 * @property JobOrderType $type
 * @property JobOrderStatus $status
 * @property Carbon|null $due_at
 * @property string|null $file_path
 * @property int|null $assigned_artist_id
 * @property string|null $validation_failure_reason
 * @property string|null $consultation_notes
 * @property PaymentStatus $payment_status
 * @property int|null $pricing_entry_id
 * @property float|null $base_price_snapshot
 * @property bool $rush_fee_applied
 * @property float|null $rush_fee_amount
 * @property string|null $discount_type
 * @property float|null $discount_value
 * @property float|null $discount_amount
 * @property float|null $total_amount
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property float|null $amount_paid Not a persisted column — only present
 *                                   when eager-loaded via withSum() (Cashier Dashboard listing, D-04/D-05).
 * @property bool $is_rush The staff-declared urgency flag captured at the
 *                         counter at intake (RUSH-01). A real, NOT NULL column since
 *                         2026_09_10_120000 — it was previously computed in memory only.
 *                         ProductionBoardController::index() deliberately widens the
 *                         in-memory value with its due-date heuristic for display
 *                         (PROD-01, D-05, D-07) and never saves that widened value.
 * @property Carbon|null $ready_at Not a persisted column — only present when
 *                                 eager-loaded via withMax() over productionLogs (PROD-03, D-13);
 *                                 the moment this job order last reached ready_for_pickup.
 */
#[Fillable(['number', 'queue_entry_id', 'description', 'print_size', 'material', 'quantity', 'deadline', 'is_rush', 'client_notes', 'pricing_entry_id', 'type', 'status', 'file_path', 'consultation_notes'])]
#[ObservedBy(AuditObserver::class)]
class JobOrder extends Model
{
    /** @use HasFactory<JobOrderFactory> */
    use HasFactory;

    /**
     * Assign the public tracking token every job order needs (QR-01).
     *
     * `tracking_token` is deliberately absent from #[Fillable]: nothing
     * request-driven may ever choose it. This hook assigns the attribute
     * directly, which is not mass assignment, and it is why factories,
     * seeders and controllers all get a token for free without any of them
     * knowing the column exists. `??=` so an explicitly-set token (a test
     * pinning a known value) is respected rather than clobbered.
     */
    protected static function booted(): void
    {
        static::creating(function (JobOrder $jobOrder): void {
            $jobOrder->tracking_token ??= Str::random(32);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => JobOrderType::class,
            'status' => JobOrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'is_rush' => 'boolean',
            'base_price_snapshot' => 'decimal:2',
            'rush_fee_applied' => 'boolean',
            'rush_fee_amount' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'released_at' => 'datetime',
            'due_at' => 'datetime',
            // The date the customer was promised, captured at intake. Distinct
            // from `due_at`, which EnterProduction stamps from the SLA config.
            'deadline' => 'date',
            // Not a column — only present when eager-loaded via withMax()
            // over productionLogs. Casting it here is what makes it
            // serialize as ISO-8601 to the frontend rather than a raw
            // aggregate string.
            'ready_at' => 'datetime',
        ];
    }

    /**
     * The visit this job order was created during.
     *
     * @return BelongsTo<QueueEntry, $this>
     */
    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }

    /**
     * The artist auto-assigned to this job order (Type B only).
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedArtist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_artist_id');
    }

    /**
     * This job order's single current design file, if any (D-08).
     *
     * @return HasOne<DesignFile, $this>
     */
    public function designFile(): HasOne
    {
        return $this->hasOne(DesignFile::class);
    }

    /**
     * This job order's full Send for Review history (D-07/D-08).
     *
     * @return HasMany<RevisionLog, $this>
     */
    public function revisionLogs(): HasMany
    {
        return $this->hasMany(RevisionLog::class);
    }

    /**
     * The catalog entry this job order was priced against (D-01).
     *
     * @return BelongsTo<PricingEntry, $this>
     */
    public function pricingEntry(): BelongsTo
    {
        return $this->belongsTo(PricingEntry::class, 'pricing_entry_id');
    }

    /**
     * This job order's full payment ledger (down payments, balance
     * payments, full payments, cancellation fees).
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The single source of truth for the derived outstanding balance
     * (D-16) — total_amount minus the sum of Completed transactions.
     * Replaces the copy that previously existed independently in eight
     * other files. Safe to call on either an eager-loaded (uses the
     * loaded `transactions` collection, no extra query) or a bare
     * instance (falls back to a fresh query).
     */
    public function outstandingBalance(): float
    {
        $amountPaid = (float) ($this->relationLoaded('transactions')
            ? $this->transactions->where('status', TransactionStatus::Completed->value)->sum('amount')
            : $this->transactions()->where('status', TransactionStatus::Completed->value)->sum('amount'));

        return $this->total_amount !== null ? round((float) $this->total_amount - $amountPaid, 2) : 0.0;
    }

    /**
     * This job order's On-Credit request, if any (D-08 — at most one
     * open credit request/receivable per job order).
     *
     * @return HasOne<AccountsReceivable, $this>
     */
    public function accountsReceivable(): HasOne
    {
        return $this->hasOne(AccountsReceivable::class);
    }

    /**
     * This job order's full production stage transition history (D-09).
     *
     * @return HasMany<ProductionLog, $this>
     */
    public function productionLogs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }

    /**
     * The shop's current numbering year (D-04), scoped narrowly to this one
     * call site — mirrors QueueEntry::currentBusinessDate()'s Asia/Manila
     * scoping. config('app.timezone') stays UTC project-wide.
     */
    public static function currentNumberingYear(): int
    {
        return (int) now()->timezone('Asia/Manila')->format('Y');
    }

    /**
     * Compute the next sequential JO-{year}-{seq} number for the given
     * year, under a database lock so concurrent requests can never collide
     * (D-04, matching QueueEntry::nextForBusinessDay()'s pattern).
     *
     * The sequence is zero-padded to a MINIMUM of four digits, not a fixed
     * four — a year that reaches 10,000 job orders keeps counting
     * (JO-2026-10000, JO-2026-10001, ...), which is exactly what
     * TrackJobOrderRequest's `\d{4,}` regex already accepts.
     *
     * That variable width rules out both a plain `MAX(number)` (a
     * string-lexicographic max ranks 'JO-2026-9999' above 'JO-2026-10000')
     * and a fixed-width `substr(-4)` parse (which reads '0000' out of
     * 'JO-2026-10000'). Ordering by LENGTH() first and the string second
     * restores numeric order without a SUBSTR/CAST SQL expression, keeping
     * the query portable across SQLite and MySQL; the suffix is then parsed
     * in PHP from the known prefix length rather than a fixed offset.
     *
     * Callers MUST invoke this inside an enclosing transaction that also
     * performs the insert — the lockForUpdate() range lock is released the
     * moment this method's own transaction commits, so a caller that
     * inserts afterwards races exactly the collision the lock prevents.
     */
    public static function nextNumberForYear(int $year): string
    {
        $prefix = "JO-{$year}-";

        return DB::transaction(function () use ($prefix, $year): string {
            $maxNumber = static::query()
                ->where('number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByRaw('LENGTH(number) DESC, number DESC')
                ->value('number');

            $sequence = is_string($maxNumber)
                ? ((int) substr($maxNumber, strlen($prefix))) + 1
                : 1;

            return sprintf('JO-%d-%04d', $year, $sequence);
        });
    }
}
