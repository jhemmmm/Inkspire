<script setup lang="ts">
const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="bg-muted flex min-h-screen items-start justify-center p-4 pt-5 sm:items-center sm:p-6"
    >
        <div
            class="flex w-full max-w-[860px] flex-col overflow-hidden rounded-[20px] shadow-[0_20px_60px_rgba(0,40,142,0.12),0_4px_16px_rgba(0,0,0,0.08)] max-[699px]:max-w-[420px] md:min-h-[520px] md:flex-row"
        >
            <!-- LEFT panel: branding -->
            <div
                class="hidden flex-1 flex-col justify-center bg-white p-[48px_44px] md:flex"
            >
                <img
                    src="/logo.png"
                    alt="Inkspire"
                    class="mb-5 h-18 w-auto object-contain"
                />
                <div class="bg-primary mb-5 h-[3px] w-10 rounded-full" />
                <h2
                    class="text-foreground mb-3 text-[22px] leading-snug font-extrabold tracking-tight"
                >
                    Your all-in-one<br />print shop portal.
                </h2>
                <p
                    class="text-muted-foreground mb-8 text-[13px] leading-relaxed"
                >
                    Faster workflows, smarter order management, and real-time
                    team coordination — built for Squarefoot Graphics &amp; Ads.
                </p>
                <div class="flex items-center gap-5">
                    <div>
                        <div
                            class="text-primary text-xl leading-none font-extrabold"
                        >
                            6
                        </div>
                        <div
                            class="text-muted-foreground mt-1 text-[10px] font-semibold tracking-wider uppercase"
                        >
                            Staff Roles
                        </div>
                    </div>
                    <div class="bg-border h-7 w-px" />
                    <div>
                        <div
                            class="text-primary text-xl leading-none font-extrabold"
                        >
                            24/7
                        </div>
                        <div
                            class="text-muted-foreground mt-1 text-[10px] font-semibold tracking-wider uppercase"
                        >
                            Available
                        </div>
                    </div>
                    <div class="bg-border h-7 w-px" />
                    <div>
                        <div
                            class="text-primary text-xl leading-none font-extrabold"
                        >
                            Live
                        </div>
                        <div
                            class="text-muted-foreground mt-1 text-[10px] font-semibold tracking-wider uppercase"
                        >
                            Queue Monitor
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT panel: form -->
            <div
                class="bg-primary flex min-w-0 flex-1 flex-col items-center justify-center px-5 py-7 text-center sm:px-8 md:p-[48px_44px]"
            >
                <img
                    src="/business_logo.png"
                    alt="Squarefoot Graphics &amp; Ads"
                    class="mb-2 h-auto w-32 object-contain md:mb-3 md:w-50"
                />
                <h1
                    v-if="!title"
                    class="mb-5 text-lg font-extrabold tracking-tight text-white md:hidden"
                >
                    Staff Portal
                </h1>
                <h1
                    v-if="title"
                    class="mb-2 text-[17px] font-extrabold tracking-tight text-white md:mb-0.5"
                >
                    {{ title }}
                </h1>
                <p
                    class="mb-3.5 hidden max-w-[300px] text-[13px] font-medium tracking-wide text-white/75 md:block"
                >
                    {{ description || 'Printing Management System' }}
                </p>
                <span
                    class="mb-5 hidden items-center rounded-full border border-white/25 bg-white/15 px-3.5 py-1 text-[10px] font-bold tracking-widest text-white/90 uppercase md:inline-flex"
                >
                    Staff Portal
                </span>
                <!--
                    The panel is a coloured surface, so it has to say what
                    colour its text is. Without this every Label, link and
                    icon slotted in here inherited the LIGHT theme's
                    near-black `--foreground` and rendered black on royal
                    blue.

                    `text-primary-foreground` and not a `dark` scope: `.dark`
                    redefines `--primary`, which would repaint both this
                    panel and the white submit button's blue text.
                -->
                <div
                    class="text-primary-foreground w-full max-w-sm md:max-w-[280px]"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
