<?php

namespace App\Models;

use App\Enums\SpecificationCategory;
use App\Observers\AuditObserver;
use Database\Factories\SpecificationOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property SpecificationCategory $category
 * @property string $label
 * @property float|null $width_inches
 * @property float|null $height_inches
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['category', 'label', 'width_inches', 'height_inches', 'is_active', 'sort_order'])]
#[ObservedBy(AuditObserver::class)]
class SpecificationOption extends Model
{
    /** @use HasFactory<SpecificationOptionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SpecificationCategory::class,
            'width_inches' => 'float',
            'height_inches' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The active option labels for each category, keyed by the category's
     * string value — the exact shape the intake form's selects consume.
     *
     * @return array<string, array<int, string>>
     */
    public static function activeLabelsByCategory(): array
    {
        $grouped = static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['category', 'label'])
            ->groupBy(fn (self $option): string => $option->category->value)
            ->map(fn (Collection $options): array => $options->pluck('label')->all());

        return collect(SpecificationCategory::cases())
            ->mapWithKeys(fn (SpecificationCategory $category): array => [
                $category->value => $grouped->get($category->value, []),
            ])
            ->all();
    }

    /**
     * The active print sizes' recorded dimensions, keyed by label — lets
     * the intake form auto-fill Width/Height (ft) from a chosen print-size
     * preset (inches ÷ 12).
     *
     * @return array<string, array{width_inches: float|null, height_inches: float|null}>
     */
    public static function printSizeDimensionsByLabel(): array
    {
        return static::query()
            ->category(SpecificationCategory::PrintSize)
            ->where('is_active', true)
            ->get(['label', 'width_inches', 'height_inches'])
            ->mapWithKeys(fn (self $option): array => [
                $option->label => [
                    'width_inches' => $option->width_inches,
                    'height_inches' => $option->height_inches,
                ],
            ])
            ->all();
    }

    /**
     * Scope a query to a single specification category.
     *
     * @param  Builder<SpecificationOption>  $query
     */
    public function scopeCategory(Builder $query, SpecificationCategory $category): void
    {
        $query->where('category', $category->value);
    }
}
