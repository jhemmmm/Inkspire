import type { StatusTone } from '@/components/StatusBadge.vue';

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

/**
 * Badge tone and label for an artist's shift status
 * (App\Enums\ArtistStatus), shared by the artist dashboard and the Admin's
 * user list.
 */
const ARTIST_STATUS_BADGES: Record<string, StatusTone> = {
    available: 'success',
    on_break: 'warning',
};

export function artistStatusBadge(artistStatus: string): StatusTone {
    return ARTIST_STATUS_BADGES[artistStatus] ?? 'neutral';
}

const ARTIST_STATUS_LABELS: Record<string, string> = {
    available: 'Available',
    on_break: 'On Break',
    off_shift: 'Off Shift',
};

export function artistStatusLabel(artistStatus: string): string {
    return ARTIST_STATUS_LABELS[artistStatus] ?? 'Off Shift';
}
