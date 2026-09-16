<script setup lang="ts">
import { computed } from 'vue';

/**
 * Reusable status presentation — generic across domains (not coupled to
 * Campaign business logic). Communicates state via label text AND a
 * distinct dot/tone, never color alone.
 */
const props = defineProps<{ status: string }>();

const tones: Record<string, { bg: string; text: string; dot: string; label: string }> = {
    DRAFT: { bg: 'bg-slate-100', text: 'text-slate-700', dot: 'bg-slate-400', label: 'Draft' },
    REVIEW: { bg: 'bg-amber-50', text: 'text-amber-800', dot: 'bg-amber-500', label: 'In Review' },
    APPROVED: { bg: 'bg-sky-50', text: 'text-sky-800', dot: 'bg-sky-500', label: 'Approved' },
    PUBLISHED: { bg: 'bg-emerald-50', text: 'text-emerald-800', dot: 'bg-emerald-500', label: 'Published' },
    ACTIVE: { bg: 'bg-emerald-50', text: 'text-emerald-800', dot: 'bg-emerald-500', label: 'Active' },
    CLOSED: { bg: 'bg-slate-100', text: 'text-slate-600', dot: 'bg-slate-400', label: 'Closed' },
    ARCHIVED: { bg: 'bg-slate-100', text: 'text-slate-500', dot: 'bg-slate-400', label: 'Archived' },
};

const tone = computed(() => tones[props.status] ?? { bg: 'bg-slate-100', text: 'text-slate-600', dot: 'bg-slate-400', label: props.status });
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" :class="[tone.bg, tone.text]">
        <span class="h-1.5 w-1.5 rounded-full" :class="tone.dot"></span>
        {{ tone.label }}
    </span>
</template>
