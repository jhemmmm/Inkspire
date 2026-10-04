<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="text-foreground hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring data-[state=open]:bg-accent flex h-10 max-w-48 items-center gap-2 rounded-lg px-2 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                :aria-label="`Open profile menu for ${user.name}`"
                data-test="profile-menu-button"
            >
                <UserInfo :user="user" show-role compact-on-mobile />
                <ChevronsUpDown class="hidden size-4 shrink-0 sm:block" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="min-w-56 rounded-lg" align="end">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
