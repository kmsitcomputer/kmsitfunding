<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface PlanRow {
    ulid: string;
    status: string;
    frequency: string;
    amount_minor: number;
    currency: string;
    next_occurrence_at: string | null;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ plans: Paginated<PlanRow> }>();
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <PageHeader title="My recurring plans" description="Monthly giving plans from this account." />

        <Card :padded="false" class="mt-6">
            <EmptyState v-if="plans.data.length === 0" icon="wallet" title="No recurring plans yet" description="Your monthly giving plans will appear here." />

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="plan in plans.data" :key="plan.ulid" class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">{{ plan.amount_minor }} {{ plan.currency }} · {{ plan.frequency }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">Next: {{ plan.next_occurrence_at ?? '—' }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <StatusBadge :status="plan.status" />
                        <Link :href="`/me/recurring-plans/${plan.ulid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Manage</Link>
                    </div>
                </li>
            </ul>
        </Card>
    </div>
</template>
