<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface CampaignProp {
    ulid: string;
    name: string;
    summary: string | null;
    description_html: string | null;
    status: string;
    edit_version: number;
    starts_at: string | null;
    ends_at: string | null;
}

interface FundOption {
    ulid: string;
    name: string;
}

const props = defineProps<{
    campaign: CampaignProp;
    is_donation_eligible: boolean;
    funds: FundOption[];
    currentFund: FundOption | null;
}>();

const updateForm = useForm({
    name: props.campaign.name,
    summary: props.campaign.summary ?? '',
    description_html: props.campaign.description_html ?? '',
    fund_ulid: props.currentFund?.ulid ?? '',
    expected_edit_version: props.campaign.edit_version,
});

const submitUpdate = () => {
    updateForm.transform((data) => ({
        ...data,
        fund_ulid: data.fund_ulid === '' ? null : data.fund_ulid,
    })).patch(`/admin/campaign/campaigns/${props.campaign.ulid}`);
};

const submit = () => useForm({}).post(`/admin/campaign/campaigns/${props.campaign.ulid}/submit`);
const approve = () => useForm({}).post(`/admin/campaign/campaigns/${props.campaign.ulid}/approve`);
const publish = () => useForm({}).post(`/admin/campaign/campaigns/${props.campaign.ulid}/publish`);
const close = () => {
    if (confirm('Close this campaign? This cannot be undone.')) {
        useForm({}).post(`/admin/campaign/campaigns/${props.campaign.ulid}/close`);
    }
};

const rejectReason = ref('');
const reject = () => {
    useForm({ reason: rejectReason.value }).post(`/admin/campaign/campaigns/${props.campaign.ulid}/reject`);
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-3xl space-y-8">
            <h1 class="text-xl font-semibold text-neutral-800">{{ campaign.name }}</h1>
            <p class="text-sm text-neutral-500">
                Status: {{ campaign.status }}
                <span v-if="campaign.status === 'PUBLISHED'" :class="is_donation_eligible ? 'text-green-600' : 'text-amber-600'">
                    ({{ is_donation_eligible ? 'currently open' : 'not currently open (outside period)' }})
                </span>
            </p>

            <div class="flex flex-wrap gap-2">
                <button v-if="campaign.status === 'DRAFT'" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" @click="submit">Submit for Review</button>
                <button v-if="campaign.status === 'REVIEW'" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" @click="approve">Approve</button>
                <button v-if="campaign.status === 'APPROVED'" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" @click="publish">Publish</button>
                <button v-if="campaign.status === 'PUBLISHED'" class="rounded border px-3 py-2 text-sm text-red-600" @click="close">Close</button>
            </div>

            <div v-if="campaign.status === 'REVIEW'" class="rounded border bg-white p-4">
                <label class="block text-sm text-neutral-600">Rejection reason</label>
                <textarea v-model="rejectReason" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                <button class="mt-2 rounded border px-3 py-2 text-sm text-red-600" @click="reject">Reject to Draft</button>
            </div>

            <form class="space-y-4 rounded border bg-white p-4" @submit.prevent="submitUpdate">
                <h2 class="text-sm font-semibold text-neutral-600">Edit</h2>
                <div>
                    <label class="block text-sm text-neutral-600">Name</label>
                    <input v-model="updateForm.name" type="text" class="mt-1 w-full rounded border px-3 py-2" />
                    <p v-if="updateForm.errors.name" class="mt-1 text-sm text-red-600">{{ updateForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Summary</label>
                    <textarea v-model="updateForm.summary" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Description</label>
                    <textarea v-model="updateForm.description_html" rows="6" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Fund</label>
                    <select v-model="updateForm.fund_ulid" class="mt-1 w-full rounded border px-3 py-2">
                        <option value="">— none —</option>
                        <option v-for="fund in funds" :key="fund.ulid" :value="fund.ulid">{{ fund.name }}</option>
                    </select>
                    <p class="mt-1 text-xs text-neutral-500">A Fund must be assigned before this campaign can be published.</p>
                    <p v-if="updateForm.errors.fund_ulid" class="mt-1 text-sm text-red-600">{{ updateForm.errors.fund_ulid }}</p>
                </div>
                <button type="submit" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" :disabled="updateForm.processing || campaign.status === 'CLOSED'">
                    Save
                </button>
            </form>
        </div>
    </div>
</template>
