import type { StatusTone } from '@/components/StatusBadge.vue';

/**
 * Accounts receivable presentation shared by the AR list and the AR detail
 * page: the labels and badge tone for an entry's aging bracket and
 * collection status.
 */
export const BRACKET_LABELS: Record<string, string> = {
    current: 'Current',
    one_to_fifteen: '1-15 Days',
    sixteen_to_thirty: '16-30 Days',
    thirty_one_to_sixty: '31-60 Days',
    sixty_one_to_ninety: '61-90 Days',
    ninety_plus: '90+ Days',
};

/** What the customer email says at each age, named for the staff sending it. */
export const CUSTOMER_EMAIL_LABELS: Record<string, string> = {
    current: 'a friendly reminder that nothing is overdue yet',
    one_to_fifteen: 'a friendly past-due reminder',
    sixteen_to_thirty: 'a past-due notice asking for payment within 7 days',
    thirty_one_to_sixty: 'a formal request for full payment within 7 days',
    sixty_one_to_ninety: 'an urgent notice, the last before a final notice',
    ninety_plus: 'a final notice before collection or write-off',
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
