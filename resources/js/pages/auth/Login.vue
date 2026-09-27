<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Lock, Mail } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    authErrorClass,
    authInputClass,
    authSubmitClass,
} from '@/layouts/auth/fields';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: '',
        description: '',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-emerald-200"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label
                    for="email"
                    class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold tracking-widest text-white/70 uppercase"
                    ><Mail class="size-3.5" />Email Address</Label
                >
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                    :class="authInputClass"
                />
                <InputError :message="errors.email" :class="authErrorClass" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label
                        for="password"
                        class="flex items-center gap-1.5 text-[11px] font-bold tracking-widest text-white/70 uppercase"
                        ><Lock class="size-3.5" />Password</Label
                    >
                    <Link
                        v-if="canResetPassword"
                        :href="request()"
                        :tabindex="5"
                        class="text-xs font-medium text-white/50 transition-colors hover:text-white/90"
                    >
                        Forgot your password?
                    </Link>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                    :class="authInputClass"
                />
                <InputError
                    :message="errors.password"
                    :class="authErrorClass"
                />
            </div>

            <div class="flex items-center justify-between">
                <Label
                    for="remember"
                    class="flex items-center space-x-3 text-sm text-white/80"
                >
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="data-[state=checked]:text-primary border-white/40 focus-visible:ring-white/30 data-[state=checked]:border-white/70 data-[state=checked]:bg-white"
                    />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                :class="[authSubmitClass, 'mt-4']"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>
    </Form>
</template>
