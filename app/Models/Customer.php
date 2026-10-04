<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $organization
 * @property string $contact_number
 * @property string $email
 * @property string $address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'organization', 'contact_number', 'email', 'address'])]
#[ObservedBy(AuditObserver::class)]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * The queue visits this customer has generated.
     *
     * @return HasMany<QueueEntry, $this>
     */
    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    /**
     * Every job order this customer has placed, across all their visits.
     *
     * @return HasManyThrough<JobOrder, QueueEntry, $this>
     */
    public function jobOrders(): HasManyThrough
    {
        return $this->hasManyThrough(JobOrder::class, QueueEntry::class);
    }

    /**
     * Contains-match on name, organization or contact number: what staff
     * type to find a customer. `%` and `_` in the term are literals, and the
     * explicit ESCAPE clause makes that hold on SQLite as well as MySQL
     * (which escapes backslash by default).
     *
     * @param  Builder<Customer>  $query
     * @param  list<literal-string>  $columns  Column names written in code, never request input: they go into the SQL as they are.
     */
    public function scopeSearch(Builder $query, string $term, array $columns = ['name', 'organization', 'contact_number']): void
    {
        $pattern = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $inner) use ($pattern, $columns): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw($column.' LIKE ? ESCAPE ?', [$pattern, '\\']);
            }
        });
    }
}
