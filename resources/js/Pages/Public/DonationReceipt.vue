<script setup lang="ts">
interface DonationProp {
    ulid: string;
    status: string;
    amount_minor: number;
    currency: string;
    formatted_amount: string;
    is_anonymous: boolean;
    donor_display_name: string | null;
}

defineProps<{ donation: DonationProp }>();
</script>

<template>
    <div class="mx-auto max-w-xl px-4 py-10">
        <h1 class="text-2xl font-semibold">Thank you for your donation</h1>
        <div class="mt-6 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
            <p class="text-lg font-semibold">{{ donation.formatted_amount }}</p>
            <p class="mt-1 text-sm text-stone-500">Status: {{ donation.status }}</p>
            <p v-if="!donation.is_anonymous && donation.donor_display_name" class="mt-1 text-sm text-stone-500">
                From {{ donation.donor_display_name }}
            </p>
            <p v-if="donation.is_anonymous" class="mt-1 text-sm text-stone-500">Anonymous donation</p>
        </div>
    </div>
</template>
