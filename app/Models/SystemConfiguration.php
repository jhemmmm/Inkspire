<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $key
 * @property string $group
 * @property mixed $value
 * @property string $type
 * @property string $label
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'group', 'value', 'type', 'label', 'description'])]
#[ObservedBy(AuditObserver::class)]
class SystemConfiguration extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Get an integer-typed configuration value, cached forever until invalidated.
     */
    public static function getInt(string $key, int $default): int
    {
        $value = self::resolve($key);

        return $value === null ? $default : (int) $value;
    }

    /**
     * Get a boolean-typed configuration value, cached forever until invalidated.
     */
    public static function getBool(string $key, bool $default): bool
    {
        $value = self::resolve($key);

        return $value === null ? $default : (bool) $value;
    }

    /**
     * Get an array-typed configuration value, cached forever until invalidated.
     *
     * @param  array<int|string, mixed>  $default
     * @return array<int|string, mixed>
     */
    public static function getArray(string $key, array $default = []): array
    {
        $value = self::resolve($key);

        return $value === null ? $default : (array) $value;
    }

    /**
     * Get a string-typed configuration value, cached forever until invalidated.
     */
    public static function getString(string $key, string $default): string
    {
        $value = self::resolve($key);

        return $value === null ? $default : (string) $value;
    }

    /**
     * Forget the cached value for a configuration key.
     *
     * Must be called immediately after any write to a configuration row —
     * `rememberForever` has no TTL, so a stale cached value would otherwise
     * persist indefinitely past an admin's intended change.
     */
    public static function invalidate(string $key): void
    {
        Cache::forget("config.{$key}");
    }

    /**
     * Resolve and cache the raw decoded `value` column for a given key.
     */
    private static function resolve(string $key): mixed
    {
        return Cache::rememberForever(
            "config.{$key}",
            fn (): mixed => static::query()->where('key', $key)->first()?->value,
        );
    }
}
