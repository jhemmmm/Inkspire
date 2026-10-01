<script setup lang="ts">
import { onBeforeUnmount, computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/composables/useInitials';

const props = defineProps<{
    id: string;
    name: string;
    avatarUrl?: string | null;
    error?: string;
}>();

const { getInitials } = useInitials();

const previewUrl = ref<string | null>(null);
const pendingRemoval = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

const displayUrl = computed(
    () =>
        previewUrl.value ??
        (pendingRemoval.value ? null : (props.avatarUrl ?? null)),
);

function revokePreview(): void {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

function onFileChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    revokePreview();

    if (file) {
        previewUrl.value = URL.createObjectURL(file);
        // Choosing a new file cancels a pending removal.
        pendingRemoval.value = false;
    }
}

function removePicture(): void {
    pendingRemoval.value = true;

    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }

    revokePreview();
}

function keepPicture(): void {
    pendingRemoval.value = false;
}

onBeforeUnmount(() => {
    revokePreview();
});
</script>

<template>
    <div class="grid gap-2">
        <Label :for="`${id}-input`">Profile Picture</Label>

        <div class="flex flex-wrap items-center gap-4">
            <Avatar class="h-16 w-16">
                <AvatarImage
                    v-if="displayUrl"
                    :src="displayUrl"
                    :alt="name"
                    class="object-cover"
                />
                <AvatarFallback class="text-lg font-semibold">
                    {{ getInitials(name) }}
                </AvatarFallback>
            </Avatar>

            <div class="flex flex-wrap items-center gap-2">
                <input
                    :id="`${id}-input`"
                    ref="fileInputRef"
                    type="file"
                    name="avatar"
                    accept="image/png,image/jpeg,image/webp"
                    class="sr-only"
                    @change="onFileChange"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="fileInputRef?.click()"
                >
                    {{ displayUrl ? 'Change picture' : 'Add picture' }}
                </Button>
                <Button
                    v-if="avatarUrl && !pendingRemoval"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    @click="removePicture"
                >
                    Remove
                </Button>
                <Button
                    v-if="pendingRemoval"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="keepPicture"
                >
                    Keep picture
                </Button>
            </div>
        </div>

        <input
            type="hidden"
            name="remove_avatar"
            :value="pendingRemoval ? '1' : '0'"
        />
        <InputError :message="error" />
    </div>
</template>
