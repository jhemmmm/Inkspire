<?php

namespace App\Models;

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\AccountsReceivableStatus;
use App\Observers\AuditObserver;
use Database\Factories\AccountsReceivableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_order_id
 * @property float $balance
 * @property AccountsReceivableStatus $status
 * @property AccountsReceivableCollectionStatus $collection_status
 * @property int $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $due_at
 * @property AccountsReceivableAgingBracket|null $last_reminder_bracket
 * @property Carbon|null $last_reminder_sent_at
 * @property string|null $write_off_reason
 * @property int|null $write_off_requested_by
 * @property Carbon|null $write_off_requested_at
 * @property Carbon|null $written_off_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['job_order_id', 'balance', 'status', 'requested_by'])]
#[ObservedBy(AuditObserver::class)]
class AccountsReceivable extends Model
{
    /** @use HasFactory<AccountsReceivableFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * Eloquent's default pluralization would guess `accounts_receivables`;
     * the migration and ERD both use the singular `accounts_receivable`.
     *
     * @var string
     */
    protected $table = 'accounts_receivable';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccountsReceivableStatus::class,
            'balance' => 'decimal:2',
            'approved_at' => 'datetime',
            'due_at' => 'datetime',
            'last_reminder_bracket' => AccountsReceivableAgingBracket::class,
            'last_reminder_sent_at' => 'datetime',
            'collection_status' => AccountsReceivableCollectionStatus::class,
            'write_off_requested_at' => 'datetime',
            'written_off_at' => 'datetime',
        ];
    }

    /**
     * The job order this credit request/receivable belongs to.
     *
     * @return BelongsTo<JobOrder, $this>
     */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    /**
     * The Cashier who requested this credit.
     *
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * The Owner who approved or rejected this credit request.
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The Accounting Staff member who requested this entry be written off.
     *
     * @return BelongsTo<User, $this>
     */
    public function writeOffRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'write_off_requested_by');
    }

    /**
     * The current aging bracket (D-03), derived purely from `due_at` vs
     * now() — no query, safe to call per-row when listing many entries
     * (Pitfall 3).
     */
    public function agingBracket(): AccountsReceivableAgingBracket
    {
        if ($this->due_at === null || $this->due_at->isFuture()) {
            return AccountsReceivableAgingBracket::Current;
        }

        $daysPastDue = (int) $this->due_at->diffInDays(now());

        return match (true) {
            $daysPastDue <= 15 => AccountsReceivableAgingBracket::OneToFifteen,
            $daysPastDue <= 30 => AccountsReceivableAgingBracket::SixteenToThirty,
            $daysPastDue <= 60 => AccountsReceivableAgingBracket::ThirtyOneToSixty,
            $daysPastDue <= 90 => AccountsReceivableAgingBracket::SixtyOneToNinety,
            default => AccountsReceivableAgingBracket::NinetyPlus,
        };
    }

    /**
     * The number of days past due, or null when not yet due. Pure function
     * of the already-loaded `due_at` column — no query.
     */
    public function daysPastDue(): ?int
    {
        if ($this->due_at === null || $this->due_at->isFuture()) {
            return null;
        }

        return (int) $this->due_at->diffInDays(now());
    }
}
