<script setup lang="ts">
import QrcodeVue from 'qrcode.vue';

defineProps<{
    redirectUrl: string;
}>();
</script>

<template>
    <div class="flex items-center justify-center">
        <QrcodeVue :value="redirectUrl" :size="240" />
    </div>
</template>
