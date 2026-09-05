<?php

namespace App\Models;

use App\Enums\JobOrderStatus;
use App\Observers\AuditObserver;
use Database\Factories\ProductionLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_order_id
 * @property JobOrderStatus|null $from_status
 * @property JobOrderStatus $to_status
 * @property string|null $reason
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['job_order_id', 'from_status', 'to_status', 'reason', 'recorded_by'])]
#[ObservedBy(AuditObserver::class)]
class ProductionLog extends Model
{
    /** @use HasFactory<ProductionLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => JobOrderStatus::class,
            'to_status' => JobOrderStatus::class,
        ];
    }

    /**
     * The job order this stage transition belongs to.
     *
     * @return BelongsTo<JobOrder, $this>
     */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    /**
     * The user who recorded this transition, if any (null for a
     * system-authored entry, per D-09).
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
