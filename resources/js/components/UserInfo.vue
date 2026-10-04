<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { roleLabel } from '@/lib/roles';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    showRole?: boolean;
    compactOnMobile?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    showRole: false,
    compactOnMobile: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback
            class="from-ink-cyan to-primary text-primary-foreground rounded-lg bg-linear-to-br font-semibold"
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <!--
        Secondary lines dim with `opacity` rather than `text-muted-foreground`
        so they inherit the text colour of the header or dropdown.
    -->
    <div
        class="min-w-0 flex-1 text-left text-sm leading-tight"
        :class="compactOnMobile ? 'hidden sm:grid' : 'grid'"
    >
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs opacity-70">{{
            user.email
        }}</span>
        <span v-if="showRole" class="truncate text-xs opacity-70">{{
            roleLabel(user.role)
        }}</span>
    </div>
</template>
