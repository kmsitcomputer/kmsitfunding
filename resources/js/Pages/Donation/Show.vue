<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Card from '../../Components/UI/Card.vue';
import PageHeader from '../../Components/UI/PageHeader.vue';
import StatusBadge from '../../Components/UI/StatusBadge.vue';

interface DonationProp {
    ulid: string;
    status: string;
    amount_minor: number;
    currency: string;
    is_anonymous: boolean;
    donor_display_name: string | null;
    created_at: string;
}

const props = defineProps<{ donation: DonationProp }>();

const cancel = () => useForm({}).post(`/me/donations/${props.donation.ulid}/cancel`);
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <PageHeader title="Donation detail" :description="`${donation.amount_minor} ${donation.currency}`" />

        <Card class="mt-6">
            <div class="flex items-center gap-3">
                <StatusBadge :status="donation.status" />
                <span class="text-sm text-slate-500">{{ donation.created_at }}</span>
            </div>
            <p v-if="donation.donor_display_name" class="mt-3 text-sm text-slate-600">From {{ donation.donor_display_name }}</p>
            <p v-if="donation.is_anonymous" class="mt-3 text-sm text-slate-600">Anonymous donation</p>
            <button
                v-if="donation.status === 'PENDING'"
                type="button"
                class="mt-6 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="cancel"
            >
                Cancel donation
            </button>
        </Card>
    </div>
</template>
