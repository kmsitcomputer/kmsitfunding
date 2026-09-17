<script setup lang="ts">
withDefaults(defineProps<{ rows?: number; invalid?: boolean; placeholder?: string; id?: string }>(), { rows: 4, invalid: false });
const model = defineModel<string | null>();
</script>

<template>
    <textarea
        :id="id"
        v-model="model"
        :rows="rows"
        :placeholder="placeholder"
        class="block w-full rounded-lg border px-3 py-2 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-600/40"
        :class="invalid ? 'border-red-300 focus:border-red-400' : 'border-slate-300 focus:border-emerald-600'"
    ></textarea>
</template>
