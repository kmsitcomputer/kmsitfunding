<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ email: string; token: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/reset-password');
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
        <form class="w-full max-w-sm space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-neutral-800">Reset your password</h1>

            <div>
                <label class="block text-sm text-neutral-600">Email</label>
                <input v-model="form.email" type="email" class="mt-1 w-full rounded border px-3 py-2" required />
            </div>

            <div>
                <label class="block text-sm text-neutral-600">New password</label>
                <input v-model="form.password" type="password" class="mt-1 w-full rounded border px-3 py-2" required />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-600">Confirm new password</label>
                <input v-model="form.password_confirmation" type="password" class="mt-1 w-full rounded border px-3 py-2" required />
            </div>

            <button type="submit" class="w-full rounded bg-neutral-800 px-3 py-2 text-white" :disabled="form.processing">
                Reset password
            </button>
        </form>
    </div>
</template>
