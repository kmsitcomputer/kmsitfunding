<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

defineProps<{ status?: string | null }>();

const form = useForm({ email: '' });
const submit = () => form.post('/forgot-password');
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
        <form class="w-full max-w-sm space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-neutral-800">Forgot your password?</h1>
            <p v-if="status" class="text-sm text-green-700">{{ status }}</p>

            <div>
                <label class="block text-sm text-neutral-600">Email</label>
                <input v-model="form.email" type="email" class="mt-1 w-full rounded border px-3 py-2" required autofocus />
            </div>

            <button type="submit" class="w-full rounded bg-neutral-800 px-3 py-2 text-white" :disabled="form.processing">
                Send reset link
            </button>
        </form>
    </div>
</template>
