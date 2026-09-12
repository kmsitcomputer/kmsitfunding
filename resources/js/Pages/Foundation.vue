<script setup lang="ts">
defineProps<{
    appName: string;
}>();
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 text-neutral-800">
        <div class="text-center">
            <h1 class="text-2xl font-semibold">{{ appName }}</h1>
            <p class="mt-2 text-sm text-neutral-500">
                Foundation stage — no business features implemented yet.
            </p>
        </div>
    </div>
</template>
