import type { StatusTone } from '@/components/StatusBadge.vue';

/**
 * Accounts receivable presentation shared by the AR list and the AR detail
 * page: the labels and badge tone for an entry's aging bracket and
 * collection status.
 */
export const BRACKET_LABELS: Record<string, string> = {
    current: 'Current',
    one_to_fifteen: '1–15 Days',
    sixteen_to_thirty: '16–30 Days',
    thirty_one_to_sixty: '31–60 Days',
    sixty_one_to_ninety: '61–90 Days',
    ninety_plus: '90+ Days',
};

export const COLLECTION_STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    follow_up: 'Follow-up',
    warning_sent: 'Warning Sent',
    collections: 'Collections',
    paid: 'Paid',
    written_off: 'Written Off',
    cancelled: 'Cancelled',
};

const AGING_BADGES: Record<string, StatusTone> = {
    current: 'success',
    thirty_one_to_sixty: 'warning',
    sixty_one_to_ninety: 'warning',
    ninety_plus: 'danger',
};

export function agingBadge(bracket: string): StatusTone {
    return AGING_BADGES[bracket] ?? 'neutral';
}

const COLLECTION_STATUS_BADGES: Record<string, StatusTone> = {
    follow_up: 'warning',
    warning_sent: 'warning',
    collections: 'danger',
    paid: 'success',
};

export function collectionStatusBadge(status: string): StatusTone {
    return COLLECTION_STATUS_BADGES[status] ?? 'neutral';
}
