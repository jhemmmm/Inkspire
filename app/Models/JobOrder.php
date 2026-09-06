<?php

namespace App\Models;

use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Enums\PaymentStatus;
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

/**
 * @property int $id
 * @property string|null $number
 * @property int $queue_entry_id
 * @property string $description
 * @property JobOrderType $type
 * @property JobOrderStatus $status
 * @property Carbon|null $due_at
 * @property string|null $file_path
 * @property int|null $assigned_artist_id
 * @property string|null $validation_failure_reason
 * @property string|null $consultation_notes
 * @property Carbon|null $queue_deprioritized_at
 * @property bool $not_appeared
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
 * @property bool|null $is_rush Not a persisted column — only present when
 *                              computed by ProductionBoardController::index() (PROD-01, D-05, D-07).
 */
#[Fillable(['number', 'queue_entry_id', 'description', 'type', 'status', 'file_path', 'consultation_notes'])]
#[ObservedBy(AuditObserver::class)]
class JobOrder extends Model
{
    /** @use HasFactory<JobOrderFactory> */
    use HasFactory;

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
            'queue_deprioritized_at' => 'datetime',
            'not_appeared' => 'boolean',
            'payment_status' => PaymentStatus::class,
            'base_price_snapshot' => 'decimal:2',
            'rush_fee_applied' => 'boolean',
            'rush_fee_amount' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'released_at' => 'datetime',
            'due_at' => 'datetime',
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
     * Extracts the zero-padded 4-digit sequence suffix in PHP rather than
     * via a SUBSTR/CAST SQL expression, avoiding a SQLite/MySQL portability
     * mismatch — string-lexicographic MAX() already returns the correct row
     * because every number sharing a year prefix has the same fixed width.
     */
    public static function nextNumberForYear(int $year): string
    {
        return DB::transaction(function () use ($year): string {
            $maxNumber = static::query()
                ->where('number', 'like', "JO-{$year}-%")
                ->lockForUpdate()
                ->max('number');

            $sequence = $maxNumber === null ? 1 : ((int) substr($maxNumber, -4)) + 1;

            return sprintf('JO-%d-%04d', $year, $sequence);
        });
    }
}
