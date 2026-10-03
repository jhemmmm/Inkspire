<?php

namespace App\Models;

use Database\Factories\OnlineOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A website order waiting for its customer to confirm by email. Nothing is
 * written to customers, queue entries or job orders until it is confirmed.
 *
 * @property int $id
 * @property string $email
 * @property array{customer: array<string, mixed>, job_orders: array<int, array<string, mixed>>} $payload
 * @property Carbon|null $confirmed_at
 * @property int|null $queue_entry_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'payload', 'confirmed_at', 'queue_entry_id'])]
class OnlineOrder extends Model
{
    /** @use HasFactory<OnlineOrderFactory> */
    use HasFactory, Prunable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * The visit this order became once confirmed.
     *
     * @return BelongsTo<QueueEntry, $this>
     */
    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }

    /**
     * Orders left sitting for three days, confirmed or not.
     *
     * @return Builder<OnlineOrder>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(3));
    }

    /**
     * Delete the stored files of an order nobody confirmed. A confirmed
     * order's files now belong to its job orders and must stay.
     */
    protected function pruning(): void
    {
        if ($this->confirmed_at !== null) {
            return;
        }

        foreach ($this->payload['job_orders'] ?? [] as $row) {
            if (! empty($row['file_path'])) {
                Storage::disk('local')->delete($row['file_path']);
            }
        }
    }
}
