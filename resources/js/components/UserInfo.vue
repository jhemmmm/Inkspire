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
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    showRole: false,
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
        so they inherit whatever ground they sit on: the navy sidebar and the
        white dropdown both stay legible.
    -->
    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs opacity-70">{{
            user.email
        }}</span>
        <span v-if="showRole" class="truncate text-xs opacity-70">{{
            roleLabel(user.role)
        }}</span>
    </div>
</template>
