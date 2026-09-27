/**
 * Display labels for the six `users.role` enum values (App\Enums\UserRole).
 * Used for the portal badge in the topnav and the sidebar footer subtitle.
 */
const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    frontline_staff: 'Frontline Staff',
    artist: 'Artist',
    cashier: 'Cashier',
    production_staff: 'Production Staff',
    accounting_staff: 'Accounting Staff',
};

export function roleLabel(role: string): string {
    return ROLE_LABELS[role] ?? role.replaceAll('_', ' ');
}

export function portalLabel(role: string): string {
    return `${roleLabel(role)} Portal`;
}
