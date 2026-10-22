<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface DonationRow {
    ulid: string;
    status: string;
    amount_minor: number;
    currency: string;
    formatted_amount: string;
    is_guest: boolean;
    created_at: string;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ donations: Paginated<DonationRow> }>();
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Donations' }]" />
        </template>
        <template #header>
            <PageHeader title="Donations" description="Donor intent across all campaigns and donors." />
        </template>

        <Card :padded="false">
            <EmptyState v-if="donations.data.length === 0" icon="wallet" title="No donations yet" description="Donations will appear here once donors give." />

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="donation in donations.data" :key="donation.ulid" class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">{{ donation.formatted_amount }} · {{ donation.is_guest ? 'Guest' : 'Donor' }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ donation.created_at }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <StatusBadge :status="donation.status" />
                        <Link :href="`/admin/donation/donations/${donation.ulid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Manage</Link>
                    </div>
                </li>
            </ul>
        </Card>
    </AdminLayout>
</template>
