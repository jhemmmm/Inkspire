<?php

namespace App\Models;

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
 * @property int $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
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
}
