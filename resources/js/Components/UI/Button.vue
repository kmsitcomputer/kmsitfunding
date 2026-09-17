<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
        type?: 'button' | 'submit';
        disabled?: boolean;
        as?: 'button' | 'a';
        href?: string;
    }>(),
    { variant: 'primary', type: 'button', disabled: false, as: 'button' },
);

const base = 'inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

const variants: Record<string, string> = {
    primary: 'bg-emerald-700 text-white hover:bg-emerald-800 focus-visible:ring-emerald-700',
    secondary: 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 focus-visible:ring-slate-400',
    danger: 'bg-white text-red-700 border border-red-200 hover:bg-red-50 focus-visible:ring-red-500',
    ghost: 'text-slate-600 hover:bg-slate-100 focus-visible:ring-slate-400',
};

const classes = computed(() => `${base} ${variants[props.variant]}`);
</script>

<template>
    <a v-if="as === 'a'" :href="href" :class="classes"><slot /></a>
    <button v-else :type="type" :disabled="disabled" :class="classes"><slot /></button>
</template>
