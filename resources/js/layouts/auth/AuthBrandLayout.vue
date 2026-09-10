<script setup lang="ts">
const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="bg-muted flex min-h-screen items-center justify-center p-6">
        <div
            class="flex min-h-[520px] w-full max-w-[860px] flex-col overflow-hidden rounded-[20px] shadow-[0_20px_60px_rgba(0,40,142,0.12),0_4px_16px_rgba(0,0,0,0.08)] max-[699px]:max-w-[420px] md:flex-row"
        >
            <!-- LEFT panel: branding -->
            <div
                class="flex flex-1 flex-col justify-center bg-white p-[48px_44px]"
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
                            7
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
                class="bg-primary flex flex-1 flex-col items-center justify-center p-[48px_44px] text-center"
            >
                <img
                    src="/business_logo.png"
                    alt="Squarefoot Graphics &amp; Ads"
                    class="mb-3 h-auto w-50 object-contain"
                />
                <h1
                    v-if="title"
                    class="mb-0.5 text-[17px] font-extrabold tracking-tight text-white"
                >
                    {{ title }}
                </h1>
                <p
                    class="mb-3.5 max-w-[300px] text-[13px] font-medium tracking-wide text-white/75"
                >
                    {{ description || 'Printing Management System' }}
                </p>
                <span
                    class="mb-5 inline-flex items-center rounded-full border border-white/25 bg-white/15 px-3.5 py-1 text-[10px] font-bold tracking-widest text-white/90 uppercase"
                >
                    Staff Portal
                </span>
                <div class="w-full max-w-[280px]">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
