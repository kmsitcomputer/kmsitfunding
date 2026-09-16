<script setup lang="ts">
import { computed } from 'vue';
import Icon from '../UI/Icon.vue';

/**
 * Presentational-only step progression for the Campaign lifecycle
 * (docs/implementation/IMP-007-campaign-program-fund.md section 11,
 * HD-IMP007-01). Purely a visual read of the current status — it never
 * decides, offers, or performs a transition itself.
 */
const props = defineProps<{ status: string }>();

const steps = ['DRAFT', 'REVIEW', 'APPROVED', 'PUBLISHED', 'CLOSED'];
const labels: Record<string, string> = { DRAFT: 'Draft', REVIEW: 'Review', APPROVED: 'Approved', PUBLISHED: 'Published', CLOSED: 'Closed' };

const currentIndex = computed(() => steps.indexOf(props.status));
</script>

<template>
    <ol class="flex items-center">
        <li v-for="(step, i) in steps" :key="step" class="flex flex-1 items-center last:flex-none">
            <div class="flex flex-col items-center gap-1.5">
                <span
                    class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold"
                    :class="
                        i < currentIndex
                            ? 'bg-emerald-700 text-white'
                            : i === currentIndex
                              ? 'bg-emerald-700 text-white ring-4 ring-emerald-100'
                              : 'bg-slate-100 text-slate-400'
                    "
                >
                    <Icon v-if="i < currentIndex" name="check" class="h-3.5 w-3.5" />
                    <span v-else>{{ i + 1 }}</span>
                </span>
                <span class="text-xs font-medium" :class="i <= currentIndex ? 'text-slate-800' : 'text-slate-400'">{{ labels[step] }}</span>
            </div>
            <div v-if="i < steps.length - 1" class="mx-2 h-px flex-1" :class="i < currentIndex ? 'bg-emerald-600' : 'bg-slate-200'"></div>
        </li>
    </ol>
</template>
