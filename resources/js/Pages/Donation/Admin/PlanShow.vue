<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Card from '../../../Components/UI/Card.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface PlanProp {
    ulid: string;
    status: string;
    frequency: string;
    amount_minor: number;
    currency: string;
    formatted_amount: string;
    is_anonymous: boolean;
    starts_at: string;
    ends_at: string | null;
    next_occurrence_at: string | null;
}

const props = defineProps<{ plan: PlanProp }>();

const pause = () => useForm({}).post(`/admin/donation/recurring-plans/${props.plan.ulid}/pause`);
const resume = () => useForm({}).post(`/admin/donation/recurring-plans/${props.plan.ulid}/resume`);
const cancel = () => useForm({}).post(`/admin/donation/recurring-plans/${props.plan.ulid}/cancel`);
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Donations', href: '/admin/donation/donations' }, { label: plan.ulid }]" />
        </template>
        <template #header>
            <PageHeader title="Recurring plan" :description="`${plan.formatted_amount} · ${plan.frequency}`" />
        </template>

        <Card>
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
                    Pause (override)
                </button>
                <button
                    v-if="plan.status === 'PAUSED'"
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    @click="resume"
                >
                    Resume (override)
                </button>
                <button
                    v-if="plan.status === 'ACTIVE' || plan.status === 'PAUSED'"
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    @click="cancel"
                >
                    Cancel (override)
                </button>
            </div>
        </Card>
    </AdminLayout>
</template>
