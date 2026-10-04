<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref, useId } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

withDefaults(
    defineProps<{
        action: string;
        method?: 'post' | 'patch';
    }>(),
    { method: 'post' },
);

const open = ref(false);
const message = ref('');
const messageId = useId();

function onSuccess(): void {
    open.value = false;
    message.value = '';
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <Form
                :action="action"
                :method="method"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="onSuccess"
            >
                <DialogHeader>
                    <DialogTitle>Request design changes</DialogTitle>
                    <DialogDescription>
                        Describe what needs to change so the artist can revise
                        this version.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label :for="messageId">Requested changes</Label>
                    <Textarea
                        :id="messageId"
                        v-model="message"
                        name="message"
                        required
                        :maxlength="5000"
                        rows="5"
                        placeholder="For example, correct the name and make the heading larger."
                        :aria-invalid="Boolean(errors.message)"
                        :aria-describedby="
                            errors.message ? `${messageId}-error` : undefined
                        "
                        data-test="design-change-message"
                    />
                    <InputError
                        :id="`${messageId}-error`"
                        :message="errors.message"
                    />
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="processing"
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing || message.trim() === ''"
                        data-test="submit-design-changes-button"
                    >
                        {{ processing ? 'Sending…' : 'Submit request' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
