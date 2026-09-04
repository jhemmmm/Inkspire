<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Database\Factories\PricingEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property float $base_price
 * @property string|null $unit
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'base_price', 'unit', 'is_active'])]
#[ObservedBy(AuditObserver::class)]
class PricingEntry extends Model
{
    /** @use HasFactory<PricingEntryFactory> */
    use HasFactory;

    /**
     * The pricing catalog is stored in the `pricing_database` table (per the
     * approved ERD's table name), which doesn't match this model's default
     * pluralization.
     */
    protected $table = 'pricing_database';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
