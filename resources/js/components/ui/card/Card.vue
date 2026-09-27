<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card text-card-foreground border-border flex flex-col overflow-hidden rounded-xl border shadow-sm',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
