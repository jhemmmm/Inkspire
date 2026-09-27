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
 * The ticket as the shop writes it: R-001 for a rush visit, A-001 for a
 * regular one. The two lanes number independently, so the prefix is part of
 * the identity, not decoration.
 *
 * Mirrors QueueEntry::paddedNumber() on the server, which formats the same
 * ticket for toasts. Keep the two in step.
 */
export function queueNumberLabel(prefix: string, queueNumber: number): string {
    return `${prefix}-${String(queueNumber).padStart(3, '0')}`;
}
