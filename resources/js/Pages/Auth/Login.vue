<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

const submit = () => {
    form.post('/login', { onFinish: () => form.reset('password') });
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
        <form class="w-full max-w-sm space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-neutral-800">Log in</h1>

            <div>
                <label class="block text-sm text-neutral-600">Email</label>
                <input v-model="form.email" type="email" class="mt-1 w-full rounded border px-3 py-2" required autofocus />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-600">Password</label>
                <input v-model="form.password" type="password" class="mt-1 w-full rounded border px-3 py-2" required />
            </div>

            <button type="submit" class="w-full rounded bg-neutral-800 px-3 py-2 text-white" :disabled="form.processing">
                Log in
            </button>

            <div class="flex justify-between text-sm text-neutral-500">
                <a href="/forgot-password">Forgot password?</a>
                <a href="/donor/register">Create a donor account</a>
            </div>
        </form>
    </div>
</template>
