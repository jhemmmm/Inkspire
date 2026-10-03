<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Observers\AuditObserver;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_order_id
 * @property TransactionType $type
 * @property PaymentMethod $payment_method
 * @property float $amount
 * @property TransactionStatus $status
 * @property string|null $reference_number
 * @property string|null $paymongo_payment_intent_id
 * @property int $recorded_by
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['job_order_id', 'type', 'payment_method', 'amount', 'status', 'reference_number', 'paymongo_payment_intent_id', 'recorded_by', 'confirmed_at'])]
#[ObservedBy(AuditObserver::class)]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'payment_method' => PaymentMethod::class,
            'status' => TransactionStatus::class,
            'amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * The job order this payment event belongs to.
     *
     * @return BelongsTo<JobOrder, $this>
     */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    /**
     * The user (Cashier) who recorded this payment event.
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
