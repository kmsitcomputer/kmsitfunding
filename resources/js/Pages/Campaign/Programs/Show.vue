<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import FormField from '../../../Components/UI/FormField.vue';
import Icon from '../../../Components/UI/Icon.vue';
import Input from '../../../Components/UI/Input.vue';
import MediaGallery from '../../../Components/UI/MediaGallery.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';
import Textarea from '../../../Components/UI/Textarea.vue';

interface ProgramProp {
    ulid: string;
    name: string;
    summary: string | null;
    description_html: string | null;
    status: string;
    edit_version: number;
}

interface CampaignRow {
    ulid: string;
    name: string;
    status: string;
}

interface MediaAsset {
    ulid: string;
    url: string;
    original_filename: string;
}

const props = defineProps<{
    program: ProgramProp;
    campaigns: CampaignRow[];
    mediaAssets: MediaAsset[];
}>();

const updateForm = useForm({
    name: props.program.name,
    summary: props.program.summary ?? '',
    description_html: props.program.description_html ?? '',
    expected_edit_version: props.program.edit_version,
});

const submitUpdate = () => updateForm.patch(`/admin/campaign/programs/${props.program.ulid}`);

const publish = () => useForm({}).post(`/admin/campaign/programs/${props.program.ulid}/publish`);
const unpublish = () => useForm({}).post(`/admin/campaign/programs/${props.program.ulid}/unpublish`);
const archive = () => {
    if (confirm('Archive this program?')) {
        useForm({}).post(`/admin/campaign/programs/${props.program.ulid}/archive`);
    }
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Programs', href: '/admin/campaign/programs' }, { label: program.name }]" />
        </template>
        <template #header>
            <PageHeader :title="program.name" description="Program">
                <template #badge>
                    <StatusBadge :status="program.status" />
                </template>
                <template #actions>
                    <Button v-if="program.status === 'DRAFT'" @click="publish">Publish</Button>
                    <Button v-if="program.status === 'PUBLISHED'" variant="secondary" @click="unpublish">Unpublish</Button>
                    <Button v-if="program.status !== 'ARCHIVED'" variant="danger" @click="archive">Archive</Button>
                </template>
            </PageHeader>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <h2 class="text-sm font-semibold text-slate-800">Program information</h2>
                    <form class="mt-4 space-y-5" @submit.prevent="submitUpdate">
                        <FormField label="Name" for="p-name" :error="updateForm.errors.name">
                            <Input id="p-name" v-model="updateForm.name" />
                        </FormField>
                        <FormField label="Summary" for="p-summary">
                            <Textarea id="p-summary" v-model="updateForm.summary" :rows="2" />
                        </FormField>
                        <FormField label="Description" for="p-description">
                            <Textarea id="p-description" v-model="updateForm.description_html" :rows="6" />
                        </FormField>
                        <Button type="submit" :disabled="updateForm.processing">Save changes</Button>
                    </form>
                </Card>

                <Card>
                    <h2 class="text-sm font-semibold text-slate-800">Program media</h2>
                    <p class="mt-1 text-sm text-slate-500">Used for the public program page.</p>
                    <div class="mt-4">
                        <MediaGallery
                            :assets="mediaAssets"
                            :upload-url="`/admin/campaign/programs/${program.ulid}/media`"
                            :archive-url-for="(ulid) => `/admin/campaign/programs/media/${ulid}/archive`"
                        />
                    </div>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <h2 class="text-sm font-semibold text-slate-800">Campaigns</h2>
                    <ul v-if="campaigns.length > 0" class="mt-3 divide-y divide-slate-100">
                        <li v-for="campaign in campaigns" :key="campaign.ulid" class="flex items-center justify-between gap-3 py-2.5">
                            <Link :href="`/admin/campaign/campaigns/${campaign.ulid}`" class="min-w-0 truncate text-sm text-slate-700 hover:text-emerald-700">
                                {{ campaign.name }}
                            </Link>
                            <StatusBadge :status="campaign.status" />
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-slate-500">No campaigns attached yet.</p>
                    <Button as="a" href="/admin/campaign/campaigns/create" variant="secondary" class="mt-4 w-full">
                        <Icon name="plus" class="h-4 w-4" />
                        New campaign
                    </Button>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
