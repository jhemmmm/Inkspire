<script setup lang="ts">
import QrcodeVue from 'qrcode.vue';

defineProps<{
    trackingUrl: string;
}>();
</script>

<template>
    <div class="flex items-center justify-center">
        <QrcodeVue :value="trackingUrl" :size="160" render-as="svg" level="M" />
    </div>
</template>
