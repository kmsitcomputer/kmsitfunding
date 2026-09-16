<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface CampaignRow {
    ulid: string;
    name: string;
    status: string;
    updated_at: string;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ campaigns: Paginated<CampaignRow> }>();
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Campaigns' }]" />
        </template>
        <template #header>
            <PageHeader title="Campaigns" description="Fundraising initiatives moving through review, approval, and publication.">
                <template #actions>
                    <Button as="a" href="/admin/campaign/campaigns/create">New campaign</Button>
                </template>
            </PageHeader>
        </template>

        <Card :padded="false">
            <EmptyState v-if="campaigns.data.length === 0" icon="megaphone" title="No campaigns yet" description="Create your first campaign to start the review workflow.">
                <template #action>
                    <Button as="a" href="/admin/campaign/campaigns/create">New campaign</Button>
                </template>
            </EmptyState>

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="campaign in campaigns.data" :key="campaign.ulid" class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">{{ campaign.name }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">Updated {{ campaign.updated_at }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <StatusBadge :status="campaign.status" />
                        <Link :href="`/admin/campaign/campaigns/${campaign.ulid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Manage</Link>
                    </div>
                </li>
            </ul>
        </Card>
    </AdminLayout>
</template>
