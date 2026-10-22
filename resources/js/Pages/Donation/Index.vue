<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Card from '../../Components/UI/Card.vue';
import EmptyState from '../../Components/UI/EmptyState.vue';
import PageHeader from '../../Components/UI/PageHeader.vue';
import StatusBadge from '../../Components/UI/StatusBadge.vue';

interface DonationRow {
    ulid: string;
    status: string;
    amount_minor: number;
    currency: string;
    is_anonymous: boolean;
    donor_display_name: string | null;
    created_at: string;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ donations: Paginated<DonationRow> }>();
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <PageHeader title="My donations" description="Donations you have made from this account." />

        <Card :padded="false" class="mt-6">
            <EmptyState v-if="donations.data.length === 0" icon="wallet" title="No donations yet" description="Your donations will appear here." />

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="donation in donations.data" :key="donation.ulid" class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">{{ donation.amount_minor }} {{ donation.currency }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ donation.created_at }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <StatusBadge :status="donation.status" />
                        <Link :href="`/me/donations/${donation.ulid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">View</Link>
                    </div>
                </li>
            </ul>
        </Card>
    </div>
</template>
