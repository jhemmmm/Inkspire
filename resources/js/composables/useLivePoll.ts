import { router, usePoll } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed, onUnmounted, watch } from 'vue';

/**
 * Marks a request as a background refresh. EnforceIdleSessionTimeout reads
 * it (POLL_HEADER, which must match) so a dashboard left open on an
 * unattended screen still times out.
 */
const POLL_HEADER = 'X-Inkspire-Poll';

/**
 * Keep the named page props fresh without a reload, so work another role
 * just did shows up on its own. Only the listed props are re-fetched; local
 * state (open dialogs, filters, half-typed forms) is left alone.
 *
 * Inertia slows the poll down while the tab is in the background and stops
 * it when the page unmounts.
 *
 * The poll is paused while any other visit is in flight. A poll reloads the
 * URL as it stands, so one that started during a search or a page change
 * would land after it and put the old results, and the old URL, back.
 */
export function useLivePoll(only: string[]): void {
    const { start, stop } = usePoll(5000, {
        only,
        headers: { [POLL_HEADER]: 'true' },
    });

    let visitsInFlight = 0;

    const offStart = router.on('start', (event) => {
        if (!event.detail.visit.async) {
            visitsInFlight++;
            stop();
        }
    });

    const offFinish = router.on('finish', (event) => {
        if (!event.detail.visit.async && --visitsInFlight <= 0) {
            visitsInFlight = 0;
            start();
        }
    });

    onUnmounted(() => {
        offStart();
        offFinish();
    });
}

/**
 * Stages a job order can never leave. Polling past one of them burns the
 * per-IP rate-limit bucket forever on every device that ever opened the
 * order, without the answer ever changing again.
 */
const TERMINAL_STAGES = ['Completed', 'Cancelled'];

/**
 * The public tracking pages' poll of their `result` prop: every 5s, like the
 * queue display, but only while a found order can still move. Returns whether
 * the page is live, for its "updates on its own" note.
 */
export function useTrackingPoll(
    result: () => { found: boolean; stage?: string } | null,
): ComputedRef<boolean> {
    const { start, stop } = usePoll(
        5000,
        { only: ['result'] },
        { autoStart: false },
    );

    const isLive = computed(() => {
        const current = result();

        return (
            current?.found === true &&
            !TERMINAL_STAGES.includes(current.stage ?? '')
        );
    });

    watch(isLive, (live) => (live ? start() : stop()), { immediate: true });

    return isLive;
}
