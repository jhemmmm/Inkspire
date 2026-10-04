import { usePage } from '@inertiajs/vue3';

type DateOptions = Intl.DateTimeFormatOptions;

function dateParts(date: Date, timeZone: string): Record<string, string> {
    return Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        })
            .formatToParts(date)
            .map(({ type, value }) => [type, value]),
    );
}

export function useBusinessTime() {
    const timeZone = usePage().props.businessTimezone;

    function calendarDay(value: Date | string = new Date()): string {
        const { year, month, day } = dateParts(new Date(value), timeZone);
        return `${year}-${month}-${day}`;
    }

    function shiftDay(value: string, days: number): string {
        const date = new Date(`${value}T00:00:00Z`);
        date.setUTCDate(date.getUTCDate() + days);
        return date.toISOString().slice(0, 10);
    }

    function formatDay(value: string, options: DateOptions): string {
        const [year, month, day] = value.slice(0, 10).split('-').map(Number);
        return new Intl.DateTimeFormat('en-PH', {
            ...options,
            timeZone: 'UTC',
        }).format(new Date(Date.UTC(year, month - 1, day)));
    }

    function formatInstant(value: Date | string, options: DateOptions): string {
        return new Intl.DateTimeFormat('en-PH', {
            ...options,
            timeZone,
        }).format(new Date(value));
    }

    function formatTimestamp(value: Date | string): string {
        return formatInstant(value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    }

    return { calendarDay, shiftDay, formatDay, formatInstant, formatTimestamp };
}
