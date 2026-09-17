<script setup lang="ts">
import Icon from './Icon.vue';

withDefaults(defineProps<{ tone?: 'info' | 'warning' | 'success' | 'danger' }>(), { tone: 'info' });

const tones: Record<string, string> = {
    info: 'bg-sky-50 text-sky-800 border-sky-100',
    warning: 'bg-amber-50 text-amber-800 border-amber-100',
    success: 'bg-emerald-50 text-emerald-800 border-emerald-100',
    danger: 'bg-red-50 text-red-800 border-red-100',
};
</script>

<template>
    <div class="flex items-start gap-2.5 rounded-lg border px-3.5 py-3 text-sm" :class="tones[tone]">
        <Icon name="warning" class="mt-0.5 h-4 w-4 shrink-0" />
        <div><slot /></div>
    </div>
</template>
