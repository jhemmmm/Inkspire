<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    message?: string;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div v-show="props.message">
        <p :class="cn('text-sm text-red-600 dark:text-red-500', props.class)">
            {{ props.message }}
        </p>
    </div>
</template>
