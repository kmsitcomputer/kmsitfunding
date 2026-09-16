<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

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
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-3xl space-y-8">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-neutral-800">Campaigns</h1>
                <Link href="/admin/campaign/campaigns/create" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white">New Campaign</Link>
            </div>

            <table class="w-full rounded border bg-white text-sm">
                <thead>
                    <tr class="border-b text-left text-neutral-500">
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Updated</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="campaign in campaigns.data" :key="campaign.ulid" class="border-b last:border-0">
                        <td class="px-3 py-2 text-neutral-800">{{ campaign.name }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ campaign.status }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ campaign.updated_at }}</td>
                        <td class="px-3 py-2 text-right">
                            <Link :href="`/admin/campaign/campaigns/${campaign.ulid}`" class="text-neutral-800 underline">Manage</Link>
                        </td>
                    </tr>
                    <tr v-if="campaigns.data.length === 0">
                        <td class="px-3 py-6 text-center text-neutral-500" colspan="4">No campaigns yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
