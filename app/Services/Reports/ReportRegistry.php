<?php

namespace App\Services\Reports;

use App\Enums\UserRole;
use App\Models\User;

/**
 * The single server-side entitlement boundary for the shared Reports page
 * (D-04). A plain static registry, not a Gate::define() (no `Gate::` call
 * exists anywhere in this codebase yet -- RESEARCH.md Assumption A3).
 */
final class ReportRegistry
{
    /**
     * The ordered report catalog -- this order becomes each role's picker
     * order and default-selected report (first entitled key).
     *
     * @return array<string, array{title: string, subLine: string, badge: string, columns: list<string>, roles: list<UserRole>}>
     */
    public static function definitions(): array
    {
        return [
            'sales' => [
                'title' => 'Sales',
                'subLine' => 'Every payment that cleared in this range, by method and job order.',
                'badge' => 'Sales',
                'columns' => ['Date', 'Job Order', 'Customer', 'Type', 'Method', 'Amount'],
                'roles' => [UserRole::Owner, UserRole::Cashier, UserRole::AccountingStaff],
            ],
            'cancellations' => [
                'title' => 'Cancellations',
                'subLine' => 'Job orders cancelled in this range, with and without a cancellation fee.',
                'badge' => 'Cancellations',
                'columns' => ['Date', 'Job Order', 'Customer', 'Job Order Total', 'Cancellation Fee', 'Payment Status'],
                'roles' => [UserRole::Owner, UserRole::Cashier],
            ],
            'production-status' => [
                'title' => 'Production Status',
                'subLine' => 'Where each job order stands, with its urgency and stage.',
                'badge' => 'Production',
                'columns' => ['Job Order', 'Customer', 'Product', 'Stage', 'Urgency', 'Entered Production', 'Due'],
                'roles' => [UserRole::Owner, UserRole::ProductionStaff],
            ],
            'expenses' => [
                'title' => 'Expenses',
                'subLine' => 'Every expense recorded in this range, by category.',
                'badge' => 'Expenses',
                'columns' => ['Date', 'Category', 'Description', 'Amount', 'Recorded By', 'Status'],
                'roles' => [UserRole::Owner, UserRole::AccountingStaff],
            ],
            // Used only by Plan 08-04's xlsx export -- this key has no
            // on-screen row table (rows/rowsTotal stay empty for it).
            'financial-summary' => [
                'title' => 'Summary of Sales & Expenses',
                'subLine' => 'Revenue less expenses for this range, with the profit result.',
                'badge' => 'Financial',
                'columns' => ['Label', 'Amount'],
                'roles' => [UserRole::Owner, UserRole::AccountingStaff],
            ],
        ];
    }

    /**
     * Whether the given user's role is entitled to the given report key.
     * The single check every read and export route must run before any
     * query is built (T-08-08).
     */
    public static function isEntitled(User $user, string $key): bool
    {
        return in_array($user->role, self::definitions()[$key]['roles'] ?? [], true);
    }

    /**
     * The subset of the registry this user's role may see, in declared
     * order. Never send the full, unfiltered registry to the client
     * (T-08-10).
     *
     * @return array<string, array{title: string, subLine: string, badge: string, columns: list<string>, roles: list<UserRole>}>
     */
    public static function entitledFor(User $user): array
    {
        return array_filter(
            self::definitions(),
            fn (array $definition): bool => in_array($user->role, $definition['roles'], true),
        );
    }
}
