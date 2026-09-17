<script setup lang="ts">
import Icon from './Icon.vue';

withDefaults(defineProps<{ icon?: string; title: string; description?: string }>(), { icon: 'document' });
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center">
        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <Icon :name="icon" class="h-5 w-5" />
        </div>
        <p class="mt-3 text-sm font-medium text-slate-700">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-slate-500">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4"><slot name="action" /></div>
    </div>
</template>
