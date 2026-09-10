<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ArtistStatus;
use App\Enums\UserRole;
use App\Observers\AuditObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property UserRole $role
 * @property string|null $artist_label
 * @property bool $is_active
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property string|null $current_session_id
 * @property Carbon|null $last_activity_at
 * @property bool $is_available
 * @property Carbon|null $last_assigned_at
 * @property ArtistStatus $artist_status
 * @property Carbon|null $break_started_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'artist_label'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[ObservedBy(AuditObserver::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_available' => 'boolean',
            'last_assigned_at' => 'datetime',
            'artist_status' => ArtistStatus::class,
            'break_started_at' => 'datetime',
        ];
    }

    /**
     * The next free "Artist N" label.
     *
     * Fills the lowest gap rather than counting rows: the shop's artists are
     * numbered 1..N and a departing artist frees their number for the next
     * hire, so `count() + 1` would skip a free slot and eventually collide
     * with a reactivated account. Deactivated artists still hold their
     * label -- they have not left, and their historical job orders still
     * refer to them.
     */
    public static function nextArtistLabel(): string
    {
        $taken = static::query()
            ->where('role', UserRole::Artist->value)
            ->pluck('artist_label')
            ->filter()
            ->flip();

        for ($number = 1; $taken->has('Artist '.$number); $number++) {
            // Intentionally empty -- the guard is the whole loop.
        }

        return 'Artist '.$number;
    }
}
