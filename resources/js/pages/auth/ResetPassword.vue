<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    authErrorClass,
    authInputClass,
    authSubmitClass,
} from '@/layouts/auth/fields';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Reset password',
        description: 'Please enter your new password below',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Reset password" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    v-model="inputEmail"
                    :class="[authInputClass, 'mt-1 block w-full']"
                    readonly
                />
                <InputError
                    :message="errors.email"
                    :class="[authErrorClass, 'mt-2']"
                />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    :class="[authInputClass, 'mt-1 block w-full']"
                    autofocus
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError
                    :message="errors.password"
                    :class="authErrorClass"
                />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation"> Confirm password </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :class="[authInputClass, 'mt-1 block w-full']"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError
                    :message="errors.password_confirmation"
                    :class="authErrorClass"
                />
            </div>

            <Button
                type="submit"
                :class="[authSubmitClass, 'mt-4']"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                Reset password
            </Button>
        </div>
    </Form>
</template>
