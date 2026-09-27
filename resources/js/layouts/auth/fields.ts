/**
 * Field styling for the auth panel's blue surface.
 *
 * The panel is a deep blue, so a default Input (white) and a default Button
 * (`bg-primary`, the same blue) both fail on it — the button in particular
 * rendered as floating white text with no visible shape. These translucent
 * treatments are what Login already used; sharing them keeps the four auth
 * screens looking like one screen instead of four.
 */
export const authInputClass =
    'h-11 rounded-[10px] border-[1.5px] border-white/20 bg-white/12 px-3.5 text-white selection:bg-white/25 selection:text-white placeholder:text-white/35 focus-visible:border-white/70 focus-visible:ring-[3px] focus-visible:ring-white/20 dark:border-white/20 dark:bg-white/12 dark:text-white';

/** The panel's primary action: white on blue, never blue on blue. */
export const authSubmitClass =
    'text-primary w-full bg-white hover:bg-white/90 focus-visible:ring-white/40';

/** Secondary copy on the panel — readable, but quieter than a label. */
export const authMutedClass = 'text-white/70';

/**
 * Validation errors on the panel. The default red-600 is a dark red, which
 * on royal blue is barely legible — the one colour on this surface that
 * must never be missed.
 */
export const authErrorClass = 'text-red-300';
