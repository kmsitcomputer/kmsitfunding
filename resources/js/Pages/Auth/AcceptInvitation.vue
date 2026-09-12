<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ invitation: string }>();

const form = useForm({
    token: new URLSearchParams(window.location.search).get('token') ?? '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(window.location.pathname.replace('/accept', '/accept'));
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
        <form class="w-full max-w-sm space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-neutral-800">Accept invitation</h1>
            <p class="text-sm text-neutral-500">
                Set up your account credentials to accept this invitation. This creates only an
                authenticated identity — no business authority is granted here.
            </p>

            <div>
                <label class="block text-sm text-neutral-600">Email</label>
                <input v-model="form.email" type="email" class="mt-1 w-full rounded border px-3 py-2" required autofocus />
            </div>

            <div>
                <label class="block text-sm text-neutral-600">Password</label>
                <input v-model="form.password" type="password" class="mt-1 w-full rounded border px-3 py-2" required />
                <p v-if="form.errors.token" class="mt-1 text-sm text-red-600">{{ form.errors.token }}</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-600">Confirm password</label>
                <input v-model="form.password_confirmation" type="password" class="mt-1 w-full rounded border px-3 py-2" required />
            </div>

            <button type="submit" class="w-full rounded bg-neutral-800 px-3 py-2 text-white" :disabled="form.processing">
                Accept invitation
            </button>
        </form>
    </div>
</template>
