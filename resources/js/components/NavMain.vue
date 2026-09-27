<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel
            class="text-[11px] font-bold tracking-[0.1em] uppercase"
        >
            Navigation
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <!--
                    The active page gets a brand-red rail that grows in from
                    its centre -- a "you are here" mark, not a control fill,
                    so it stays on the identity side of the brand/destructive
                    split in app.css.
                -->
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="before:bg-sidebar-primary data-[active=true]:text-sidebar-accent-foreground relative h-11 gap-3 px-3 text-[15px] font-medium before:absolute before:inset-y-2 before:left-0 before:w-1 before:scale-y-0 before:rounded-r-full before:transition-transform before:duration-200 data-[active=true]:font-semibold data-[active=true]:before:scale-y-100 motion-reduce:before:transition-none [&>svg]:size-5"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
