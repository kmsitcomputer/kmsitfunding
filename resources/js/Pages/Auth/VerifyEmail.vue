<script setup lang="ts">
import { router } from '@inertiajs/vue3';

defineProps<{ status?: string | null }>();

const resend = () => router.post('/verify-email/resend');
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
        <div class="w-full max-w-sm space-y-4 text-center">
            <h1 class="text-xl font-semibold text-neutral-800">Verify your email</h1>
            <p class="text-sm text-neutral-500">
                We sent a verification link to your email address. Please click it to continue.
            </p>
            <p v-if="status === 'verification-link-sent'" class="text-sm text-green-700">
                A new verification link has been sent.
            </p>
            <button class="rounded bg-neutral-800 px-3 py-2 text-white" @click="resend">
                Resend verification email
            </button>
        </div>
    </div>
</template>
