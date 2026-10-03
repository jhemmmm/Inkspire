<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import JobOrderController from '@/actions/App/Http/Controllers/FrontlineStaff/JobOrderController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    jobOrderId: number;
}>();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="JobOrderController.replaceFile.form(props.jobOrderId)"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Replace File</DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="new-file">New File</Label>
                    <Input id="new-file" type="file" name="file" />
                    <InputError :message="errors.file" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="submit-replace-file-button"
                    >
                        Replace File
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
