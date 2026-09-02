<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Database\Factories\DesignFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_order_id
 * @property string $file_path
 * @property Carbon|null $locked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['job_order_id', 'file_path'])]
#[ObservedBy(AuditObserver::class)]
class DesignFile extends Model
{
    /** @use HasFactory<DesignFileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
        ];
    }

    /**
     * The job order this design file belongs to.
     *
     * @return BelongsTo<JobOrder, $this>
     */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }
}
