<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ secret: string; otpauth_uri: string }>();

const form = useForm({ code: '' });
const submit = () => form.post('/account/mfa/confirm');
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
        <form class="w-full max-w-sm space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-neutral-800">Enable two-factor authentication</h1>
            <p class="text-sm text-neutral-500">
                Add this key to your authenticator app, then enter a generated code to confirm.
            </p>

            <div class="rounded border bg-white p-3 text-center">
                <p class="break-all font-mono text-sm">{{ props.secret }}</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-600">Authenticator code</label>
                <input v-model="form.code" type="text" inputmode="numeric" class="mt-1 w-full rounded border px-3 py-2" required autofocus />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>

            <button type="submit" class="w-full rounded bg-neutral-800 px-3 py-2 text-white" :disabled="form.processing">
                Confirm and enable
            </button>
        </form>
    </div>
</template>
