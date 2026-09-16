<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface CampaignRow {
    ulid: string;
    slug: string;
    name: string;
    summary: string | null;
    program_name: string | null;
    formatted_target_amount: string | null;
    is_donation_eligible: boolean;
    cover_image_url: string | null;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ campaigns: Paginated<CampaignRow> }>();
</script>

<template>
    <div class="min-h-screen bg-stone-50">
        <header class="border-b border-stone-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Campaigns</h1>
                <p class="mt-2 max-w-2xl text-stone-600">Ongoing and upcoming fundraising campaigns you can support.</p>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <div v-if="campaigns.data.length > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="campaign in campaigns.data"
                    :key="campaign.ulid"
                    :href="`/campaigns/${campaign.slug}`"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:shadow-md"
                >
                    <div class="aspect-[16/9] w-full overflow-hidden bg-stone-100">
                        <img v-if="campaign.cover_image_url" :src="campaign.cover_image_url" :alt="campaign.name" class="h-full w-full object-cover transition group-hover:scale-[1.02]" />
                        <div v-else class="flex h-full w-full items-center justify-center text-stone-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" class="h-10 w-10"><path d="M4 5h16v14H4V5Zm2 11 4-4 3 3 3-4 4 5H6Z" /></svg>
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-5">
                        <p v-if="campaign.program_name" class="text-xs font-medium uppercase tracking-wide text-emerald-700">{{ campaign.program_name }}</p>
                        <h2 class="text-base font-semibold text-stone-900">{{ campaign.name }}</h2>
                        <p v-if="campaign.summary" class="line-clamp-2 flex-1 text-sm text-stone-600">{{ campaign.summary }}</p>
                        <div class="mt-2 flex items-center justify-between border-t border-stone-100 pt-3">
                            <span v-if="campaign.formatted_target_amount" class="text-sm font-medium text-stone-800">{{ campaign.formatted_target_amount }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="campaign.is_donation_eligible ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                            >
                                {{ campaign.is_donation_eligible ? 'Open' : 'Not currently open' }}
                            </span>
                        </div>
                    </div>
                </Link>
            </div>
            <p v-else class="py-16 text-center text-stone-500">No campaigns published yet.</p>
        </main>
    </div>
</template>
