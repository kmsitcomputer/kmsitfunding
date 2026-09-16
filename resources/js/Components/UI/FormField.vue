<script setup lang="ts">
defineProps<{ label: string; error?: string; help?: string; for?: string }>();
</script>

<template>
    <div>
        <label :for="$props.for" class="block text-sm font-medium text-slate-700">{{ label }}</label>
        <div class="mt-1.5">
            <slot />
        </div>
        <p v-if="help && !error" class="mt-1.5 text-xs text-slate-500">{{ help }}</p>
        <p v-if="error" class="mt-1.5 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
