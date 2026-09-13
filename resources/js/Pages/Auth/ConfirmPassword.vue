<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });
const submit = () => form.post('/account/confirm-password');
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
        <form class="w-full max-w-sm space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-neutral-800">Confirm your password</h1>
            <p class="text-sm text-neutral-500">This is a sensitive action — please confirm your password to continue.</p>

            <div>
                <input v-model="form.password" type="password" class="mt-1 w-full rounded border px-3 py-2" required autofocus />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <button type="submit" class="w-full rounded bg-neutral-800 px-3 py-2 text-white" :disabled="form.processing">
                Confirm
            </button>
        </form>
    </div>
</template>
