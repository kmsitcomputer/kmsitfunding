<script setup lang="ts">
interface Branding {
    color_tokens: Record<string, string>;
    logo_url: string | null;
}

defineProps<{
    template: { branding: Branding };
}>();
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-neutral-50 px-4 text-center">
        <img v-if="template.branding.logo_url" :src="template.branding.logo_url" alt="Logo" class="mb-6 h-8" />
        <h1 class="text-3xl font-semibold text-neutral-800">Page not found</h1>
        <p class="mt-2 text-neutral-600">The page you're looking for doesn't exist or is no longer available.</p>
    </div>
</template>
