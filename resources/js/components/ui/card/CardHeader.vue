<script setup lang="ts">
import type { Component, HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
  icon?: Component
}>()
</script>

<template>
  <div
    data-slot="card-header"
    :class="cn('bg-muted/40 border-border flex items-center gap-3 border-b px-6 py-4', props.class)"
  >
    <span
      v-if="icon"
      class="bg-accent text-accent-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
    >
      <component :is="icon" class="size-[18px]" />
    </span>
    <div class="min-w-0 flex-1">
      <slot />
    </div>
  </div>
</template>
