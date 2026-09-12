<script setup lang="ts">
import { router } from '@inertiajs/vue3';

const logout = () => router.post('/logout');
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50">
        <div class="text-center">
            <h1 class="text-2xl font-semibold">You're signed in.</h1>
            <p class="mt-2 text-sm text-neutral-500">
                Neutral authenticated landing — no business dashboard exists yet.
            </p>
            <button class="mt-4 rounded bg-neutral-800 px-3 py-2 text-white" @click="logout">Log out</button>
        </div>
    </div>
</template>
