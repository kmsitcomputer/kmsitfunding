<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import LifecycleStepper from '../../../Components/Campaign/LifecycleStepper.vue';
import Alert from '../../../Components/UI/Alert.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import FormField from '../../../Components/UI/FormField.vue';
import Input from '../../../Components/UI/Input.vue';
import MediaGallery from '../../../Components/UI/MediaGallery.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import RichTextEditor from '../../../Components/UI/RichTextEditor.vue';
import Select from '../../../Components/UI/Select.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';
import Textarea from '../../../Components/UI/Textarea.vue';

interface CampaignProp {
    ulid: string;
    name: string;
    summary: string | null;
    description_html: string | null;
    purpose: string | null;
    status: string;
    edit_version: number;
    starts_at: string | null;
    ends_at: string | null;
    target_amount_minor: number | null;
    currency: string | null;
}

interface FundOption {
    ulid: string;
    name: string;
}

interface MediaAsset {
    ulid: string;
    url: string;
    original_filename: string;
}

const props = defineProps<{
    campaign: CampaignProp;
    is_donation_eligible: boolean;
    funds: FundOption[];
    currentFund: FundOption | null;
    mediaAssets: MediaAsset[];
}>();

const updateForm = useForm({
    name: props.campaign.name,
    summary: props.campaign.summary ?? '',
    description_html: props.campaign.description_html ?? '',
    purpose: props.campaign.purpose ?? '',
    fund_ulid: props.currentFund?.ulid ?? '',
    target_amount_minor: props.campaign.target_amount_minor,
    currency: props.campaign.currency ?? '',
    starts_at: props.campaign.starts_at?.slice(0, 10) ?? '',
    ends_at: props.campaign.ends_at?.slice(0, 10) ?? '',
    expected_edit_version: props.campaign.edit_version,
});

const isClosed = computed(() => props.campaign.status === 'CLOSED');

const submitUpdate = () => {
    updateForm
        .transform((data) => ({ ...data, fund_ulid: data.fund_ulid === '' ? null : data.fund_ulid }))
        .patch(`/admin/campaign/campaigns/${props.campaign.ulid}`);
};

const submit = () => useForm({}).post(`/admin/campaign/campaigns/${props.campaign.ulid}/submit`);
const approve = () => useForm({}).post(`/admin/campaign/campaigns/${props.campaign.ulid}/approve`);
const publish = () => useForm({}).post(`/admin/campaign/campaigns/${props.campaign.ulid}/publish`);
const close = () => {
    if (confirm('Close this campaign? This is a terminal action.')) {
        useForm({}).post(`/admin/campaign/campaigns/${props.campaign.ulid}/close`);
    }
};

const rejectOpen = ref(false);
const rejectReason = ref('');
const reject = () => {
    useForm({ reason: rejectReason.value }).post(`/admin/campaign/campaigns/${props.campaign.ulid}/reject`, {
        onSuccess: () => (rejectOpen.value = false),
    });
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Campaigns', href: '/admin/campaign/campaigns' }, { label: campaign.name }]" />
        </template>
        <template #header>
            <div class="space-y-5">
                <PageHeader :title="campaign.name" :description="campaign.purpose ?? undefined">
                    <template #badge>
                        <StatusBadge :status="campaign.status" />
                    </template>
                    <template #actions>
                        <Button v-if="campaign.status === 'DRAFT'" @click="submit">Submit for review</Button>
                        <template v-if="campaign.status === 'REVIEW'">
                            <Button variant="secondary" @click="rejectOpen = !rejectOpen">Reject</Button>
                            <Button @click="approve">Approve</Button>
                        </template>
                        <Button v-if="campaign.status === 'APPROVED'" @click="publish">Publish</Button>
                        <Button v-if="campaign.status === 'PUBLISHED'" variant="danger" @click="close">Close campaign</Button>
                    </template>
                </PageHeader>

                <Card>
                    <LifecycleStepper :status="campaign.status" />
                </Card>

                <div v-if="rejectOpen" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <FormField label="Reason for rejection" for="reject-reason">
                        <Textarea id="reject-reason" v-model="rejectReason" :rows="2" />
                    </FormField>
                    <div class="mt-3 flex gap-2">
                        <Button variant="danger" @click="reject">Confirm reject</Button>
                        <Button variant="ghost" @click="rejectOpen = false">Cancel</Button>
                    </div>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Primary -->
            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <h2 class="text-sm font-semibold text-slate-800">Campaign information</h2>
                    <form class="mt-4 space-y-5" @submit.prevent="submitUpdate">
                        <FormField label="Name" for="cc-name" :error="updateForm.errors.name">
                            <Input id="cc-name" v-model="updateForm.name" :disabled="isClosed" />
                        </FormField>
                        <FormField label="Summary" for="cc-summary">
                            <Textarea id="cc-summary" v-model="updateForm.summary" :rows="2" />
                        </FormField>
                        <FormField label="Description / story">
                            <RichTextEditor v-model="updateForm.description_html" />
                        </FormField>
                        <FormField label="Purpose" for="cc-purpose">
                            <Input id="cc-purpose" v-model="updateForm.purpose" />
                        </FormField>
                        <Alert v-if="isClosed" tone="warning">This campaign is CLOSED and content-frozen — it cannot be edited.</Alert>
                        <Button type="submit" :disabled="updateForm.processing || isClosed">Save changes</Button>
                    </form>
                </Card>

                <Card>
                    <h2 class="text-sm font-semibold text-slate-800">Campaign media</h2>
                    <p class="mt-1 text-sm text-slate-500">Used on the public campaign page.</p>
                    <div class="mt-4">
                        <MediaGallery
                            :assets="mediaAssets"
                            :upload-url="`/admin/campaign/campaigns/${campaign.ulid}/media`"
                            :archive-url-for="(ulid) => `/admin/campaign/campaigns/media/${ulid}/archive`"
                        />
                    </div>
                </Card>
            </div>

            <!-- Secondary -->
            <div class="space-y-6">
                <Card>
                    <h2 class="text-sm font-semibold text-slate-800">Availability</h2>
                    <p class="mt-2 text-sm" :class="is_donation_eligible ? 'text-emerald-700' : 'text-amber-700'">
                        {{ is_donation_eligible ? 'Currently open for donations' : 'Not currently open' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Effective availability requires PUBLISHED status plus the campaign period below — administrative status and donor-facing
                        availability are tracked separately.
                    </p>
                </Card>

                <Card>
                    <h2 class="text-sm font-semibold text-slate-800">Fund</h2>
                    <div class="mt-3">
                        <Select v-model="updateForm.fund_ulid" :disabled="isClosed">
                            <option value="">— none —</option>
                            <option v-for="fund in funds" :key="fund.ulid" :value="fund.ulid">{{ fund.name }}</option>
                        </Select>
                        <p class="mt-1.5 text-xs text-slate-500">Required (and must be Active) before this campaign can be published.</p>
                        <p v-if="updateForm.errors.fund_ulid" class="mt-1.5 text-sm text-red-600">{{ updateForm.errors.fund_ulid }}</p>
                    </div>
                </Card>

                <Card>
                    <h2 class="text-sm font-semibold text-slate-800">Target &amp; period</h2>
                    <div class="mt-3 space-y-4">
                        <FormField label="Target amount (minor units)" for="cc-amount">
                            <Input id="cc-amount" v-model.number="updateForm.target_amount_minor" type="number" min="0" :disabled="isClosed" />
                        </FormField>
                        <FormField label="Currency" for="cc-currency">
                            <Input id="cc-currency" v-model="updateForm.currency" :disabled="isClosed" />
                        </FormField>
                        <FormField label="Starts at" for="cc-starts">
                            <Input id="cc-starts" v-model="updateForm.starts_at" type="date" :disabled="isClosed" />
                        </FormField>
                        <FormField label="Ends at" for="cc-ends">
                            <Input id="cc-ends" v-model="updateForm.ends_at" type="date" :disabled="isClosed" />
                        </FormField>
                    </div>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
