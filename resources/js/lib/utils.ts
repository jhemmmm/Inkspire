import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/**
 * The queue number as the shop writes it on the ticket: 001, not 1.
 *
 * Mirrors QueueEntry::paddedNumber() on the server, which formats the same
 * number for toasts. Keep the two in step.
 */
export function queueNumberLabel(queueNumber: number): string {
    return String(queueNumber).padStart(3, '0');
}
