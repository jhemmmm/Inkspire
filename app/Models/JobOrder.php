<?php

namespace App\Models;

use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Observers\AuditObserver;
use Database\Factories\JobOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $queue_entry_id
 * @property string $description
 * @property JobOrderType $type
 * @property JobOrderStatus $status
 * @property string|null $file_path
 * @property int|null $assigned_artist_id
 * @property string|null $validation_failure_reason
 * @property string|null $consultation_notes
 * @property Carbon|null $queue_deprioritized_at
 * @property bool $not_appeared
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['queue_entry_id', 'description', 'type', 'status', 'file_path', 'consultation_notes'])]
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
}
