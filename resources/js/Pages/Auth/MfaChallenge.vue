<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const recovery = ref(false);
const form = useForm({ code: '', recovery: false });

const submit = () => {
    form.recovery = recovery.value;
    form.post('/mfa/challenge');
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
        <form class="w-full max-w-sm space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-neutral-800">Two-factor verification</h1>
            <p class="text-sm text-neutral-500">
                Enter the code from your authenticator app, or a recovery code.
            </p>

            <div>
                <input v-model="form.code" type="text" inputmode="numeric" class="mt-1 w-full rounded border px-3 py-2" required autofocus />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-neutral-600">
                <input v-model="recovery" type="checkbox" />
                This is a recovery code
            </label>

            <button type="submit" class="w-full rounded bg-neutral-800 px-3 py-2 text-white" :disabled="form.processing">
                Verify
            </button>
        </form>
    </div>
</template>
