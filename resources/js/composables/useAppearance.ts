import { router } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

/**
 * The landing page and the brand-panel auth screens were designed on white
 * and have no dark variant, so they stay light whatever the saved
 * appearance. Keep in step with `$lightOnly` in app.blade.php, which does
 * the same for the first paint.
 */
export function isLightOnlyPage(component: string): boolean {
    return (
        component === 'Welcome' ||
        component.startsWith('auth/') ||
        component.startsWith('errors/')
    );
}

let onLightOnlyPage = false;

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    const isDark =
        value === 'system'
            ? window.matchMedia('(prefers-color-scheme: dark)').matches
            : value === 'dark';

    document.documentElement.classList.toggle(
        'dark',
        isDark && !onLightOnlyPage,
    );
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    /**
     * Only follow the OS when the user has explicitly opted into 'system'.
     * Without a stored preference the app is light, so an OS flip must not
     * drag it into dark.
     */
    if (getStoredAppearance() !== 'system') {
        return;
    }

    updateTheme('system');
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Re-apply the saved preference (or light) on every visit, the initial
    // load included, so leaving a light-only page restores the user's theme...
    router.on('navigate', (event) => {
        onLightOnlyPage = isLightOnlyPage(event.detail.page.component);
        updateTheme(getStoredAppearance() || 'light');
    });

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('light');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
