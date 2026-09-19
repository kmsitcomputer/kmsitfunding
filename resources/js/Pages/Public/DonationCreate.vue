<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface CampaignProp {
    ulid: string;
    name: string;
    slug: string;
    summary: string | null;
}

const props = defineProps<{
    campaign: CampaignProp;
    is_donation_eligible: boolean;
}>();

const form = useForm({
    amount_minor: null as number | null,
    currency: 'IDR',
    is_anonymous: false as boolean,
    donor_display_name: '',
    guest_name: '',
    guest_email: '',
});

function submit() {
    form.post(`/campaigns/${props.campaign.slug}/donations`, {
        headers: { 'Idempotency-Key': crypto.randomUUID() },
    });
}

// The idempotency failure key arrives as a server-side-only error bag
// entry (the key travels as a header, never a form field) — read it
// loosely rather than extending the form shape for a non-field key.
const idempotencyError = computed(() => (form.errors as Record<string, string | undefined>)['idempotency_key'] ?? null);
</script>

<template>
    <div class="mx-auto max-w-xl px-4 py-10">
        <h1 class="text-2xl font-semibold">Donate to {{ campaign.name }}</h1>
        <p v-if="campaign.summary" class="mt-1 text-stone-500">{{ campaign.summary }}</p>

        <div v-if="!is_donation_eligible" class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
            This campaign is not currently accepting donations.
        </div>

        <form v-else class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label class="text-sm font-medium" for="amount">Amount (minor units)</label>
                <input id="amount" v-model.number="form.amount_minor" type="number" min="1" required class="mt-1 w-full rounded-lg border px-3 py-2" />
                <p v-if="form.errors.amount_minor" class="mt-1 text-sm text-red-600">{{ form.errors.amount_minor }}</p>
            </div>

            <div>
                <label class="text-sm font-medium" for="currency">Currency</label>
                <input id="currency" v-model="form.currency" type="text" maxlength="3" required class="mt-1 w-full rounded-lg border px-3 py-2" />
                <p v-if="form.errors.currency" class="mt-1 text-sm text-red-600">{{ form.errors.currency }}</p>
            </div>

            <div>
                <label class="text-sm font-medium" for="display_name">Display name (optional)</label>
                <input id="display_name" v-model="form.donor_display_name" type="text" maxlength="150" class="mt-1 w-full rounded-lg border px-3 py-2" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-medium" for="guest_name">Name (guests)</label>
                    <input id="guest_name" v-model="form.guest_name" type="text" maxlength="150" class="mt-1 w-full rounded-lg border px-3 py-2" />
                </div>
                <div>
                    <label class="text-sm font-medium" for="guest_email">Email (guests)</label>
                    <input id="guest_email" v-model="form.guest_email" type="email" maxlength="255" class="mt-1 w-full rounded-lg border px-3 py-2" />
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_anonymous" type="checkbox" class="rounded" />
                Donate anonymously
            </label>

            <p v-if="idempotencyError" class="text-sm text-red-600">{{ idempotencyError }}</p>

            <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50">
                Donate
            </button>
        </form>
    </div>
</template>
