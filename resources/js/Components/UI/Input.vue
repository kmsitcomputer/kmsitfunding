<script setup lang="ts">
withDefaults(defineProps<{ type?: string; invalid?: boolean; placeholder?: string; id?: string; min?: string | number; step?: string | number }>(), {
    type: 'text',
    invalid: false,
});
const model = defineModel<string | number | null>();
</script>

<template>
    <input
        :id="id"
        v-model="model"
        :type="type"
        :placeholder="placeholder"
        :min="min"
        :step="step"
        class="block w-full rounded-lg border px-3 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-600/40"
        :class="invalid ? 'border-red-300 focus:border-red-400' : 'border-slate-300 focus:border-emerald-600'"
    />
</template>
