<script setup lang="ts">
withDefaults(defineProps<{ invalid?: boolean; id?: string }>(), { invalid: false });
const model = defineModel<string | number | null>();
</script>

<template>
    <select
        :id="id"
        v-model="model"
        class="block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:outline-none focus:ring-2 focus:ring-emerald-600/40"
        :class="invalid ? 'border-red-300 focus:border-red-400' : 'border-slate-300 focus:border-emerald-600'"
    >
        <slot />
    </select>
</template>
