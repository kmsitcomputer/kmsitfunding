<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Card from '../../../Components/UI/Card.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface DonationProp {
    ulid: string;
    status: string;
    amount_minor: number;
    currency: string;
    formatted_amount: string;
    is_anonymous: boolean;
    is_guest: boolean;
    donor_display_name: string | null;
    guest_name: string | null;
    guest_email: string | null;
    created_at: string;
}

const props = defineProps<{ donation: DonationProp }>();

const cancel = () => useForm({}).post(`/admin/donation/donations/${props.donation.ulid}/cancel`);
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Donations', href: '/admin/donation/donations' }, { label: donation.ulid }]" />
        </template>
        <template #header>
            <PageHeader title="Donation detail" :description="donation.formatted_amount" />
        </template>

        <Card>
            <div class="flex items-center gap-3">
                <StatusBadge :status="donation.status" />
                <span class="text-sm text-slate-500">{{ donation.is_guest ? 'Guest donation' : 'Authenticated donor' }}</span>
            </div>
            <dl class="mt-4 space-y-2 text-sm text-slate-600">
                <div v-if="donation.donor_display_name"><dt class="font-medium text-slate-800">Display name</dt><dd>{{ donation.donor_display_name }}</dd></div>
                <div v-if="donation.guest_name"><dt class="font-medium text-slate-800">Guest name</dt><dd>{{ donation.guest_name }}</dd></div>
                <div v-if="donation.guest_email"><dt class="font-medium text-slate-800">Guest email</dt><dd>{{ donation.guest_email }}</dd></div>
                <div><dt class="font-medium text-slate-800">Anonymous</dt><dd>{{ donation.is_anonymous ? 'Yes' : 'No' }}</dd></div>
                <div><dt class="font-medium text-slate-800">Created</dt><dd>{{ donation.created_at }}</dd></div>
            </dl>
            <button
                v-if="donation.status === 'PENDING'"
                type="button"
                class="mt-6 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="cancel"
            >
                Cancel donation
            </button>
        </Card>
    </AdminLayout>
</template>
