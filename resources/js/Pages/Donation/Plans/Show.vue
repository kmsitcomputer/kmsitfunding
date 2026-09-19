<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Card from '../../../Components/UI/Card.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface PlanProp {
    ulid: string;
    status: string;
    frequency: string;
    amount_minor: number;
    currency: string;
    is_anonymous: boolean;
    starts_at: string;
    ends_at: string | null;
    next_occurrence_at: string | null;
}

const props = defineProps<{ plan: PlanProp }>();

const pause = () => useForm({}).post(`/me/recurring-plans/${props.plan.ulid}/pause`);
const resume = () => useForm({}).post(`/me/recurring-plans/${props.plan.ulid}/resume`);
const cancel = () => useForm({}).post(`/me/recurring-plans/${props.plan.ulid}/cancel`);
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <PageHeader title="Recurring plan" :description="`${plan.amount_minor} ${plan.currency} · ${plan.frequency}`" />

        <Card class="mt-6">
            <div class="flex items-center gap-3">
                <StatusBadge :status="plan.status" />
                <span class="text-sm text-slate-500">Next: {{ plan.next_occurrence_at ?? '—' }}</span>
            </div>
            <div class="mt-6 flex flex-wrap gap-2">
                <button
                    v-if="plan.status === 'ACTIVE'"
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    @click="pause"
                >
                    Pause
                </button>
                <button
                    v-if="plan.status === 'PAUSED'"
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    @click="resume"
                >
                    Resume
                </button>
                <button
                    v-if="plan.status === 'ACTIVE' || plan.status === 'PAUSED'"
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    @click="cancel"
                >
                    Cancel plan
                </button>
            </div>
        </Card>
    </div>
</template>
