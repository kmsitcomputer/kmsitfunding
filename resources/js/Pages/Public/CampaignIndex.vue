<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface CampaignRow {
    ulid: string;
    slug: string;
    name: string;
    summary: string | null;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ campaigns: Paginated<CampaignRow> }>();
</script>

<template>
    <div class="min-h-screen bg-neutral-50">
        <main class="mx-auto max-w-3xl px-4 py-12">
            <h1 class="text-3xl font-semibold text-neutral-800">Campaigns</h1>

            <ul class="mt-8 space-y-6">
                <li v-for="campaign in campaigns.data" :key="campaign.ulid" class="rounded border bg-white p-4">
                    <Link :href="`/campaigns/${campaign.slug}`" class="text-lg font-medium text-neutral-800 underline">
                        {{ campaign.name }}
                    </Link>
                    <p v-if="campaign.summary" class="mt-1 text-sm text-neutral-600">{{ campaign.summary }}</p>
                </li>
                <li v-if="campaigns.data.length === 0" class="text-center text-neutral-500">No campaigns published yet.</li>
            </ul>
        </main>
    </div>
</template>
