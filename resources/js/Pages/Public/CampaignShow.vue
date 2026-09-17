<script setup lang="ts">
import { computed } from 'vue';

interface CampaignProp {
    name: string;
    summary: string | null;
    description_html: string | null;
    purpose: string | null;
    starts_at: string | null;
    ends_at: string | null;
    program_name: string | null;
    cover_image_url: string | null;
}

interface Branding {
    color_tokens: Record<string, string>;
    font_family: string;
}

const props = defineProps<{
    campaign: CampaignProp;
    is_donation_eligible: boolean;
    formatted_target_amount: string | null;
    branding: Branding;
}>();

const brandStyle = computed(() => {
    const tokens = props.branding.color_tokens ?? {};
    const vars: Record<string, string> = {};
    for (const [key, value] of Object.entries(tokens)) {
        vars[`--brand-${key.replace(/_/g, '-')}`] = value;
    }
    return vars;
});

function formatDate(value: string | null): string | null {
    if (!value) return null;
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}

const availabilityMessage = computed(() => {
    if (props.is_donation_eligible) return null;
    if (props.campaign.starts_at && new Date(props.campaign.starts_at) > new Date()) {
        return `Opens on ${formatDate(props.campaign.starts_at)}`;
    }
    if (props.campaign.ends_at) {
        return `Ended on ${formatDate(props.campaign.ends_at)}`;
    }
    return null;
});
</script>

<template>
    <div :style="brandStyle" class="min-h-screen bg-[var(--brand-neutral-bg,#fafaf9)] text-[var(--brand-neutral-text,#1c1917)]">
        <div class="aspect-[21/9] w-full overflow-hidden bg-stone-200 sm:aspect-[3/1]">
            <img v-if="campaign.cover_image_url" :src="campaign.cover_image_url" :alt="campaign.name" class="h-full w-full object-cover" />
        </div>

        <main class="mx-auto -mt-10 max-w-3xl px-4 pb-16 sm:px-6">
            <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
                <p v-if="campaign.program_name" class="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent,#047857)]">
                    {{ campaign.program_name }}
                </p>
                <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">{{ campaign.name }}</h1>
                <p v-if="campaign.purpose" class="mt-1 text-stone-500">{{ campaign.purpose }}</p>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <span
                        class="rounded-full px-3 py-1 text-sm font-medium"
                        :class="is_donation_eligible ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                    >
                        {{ is_donation_eligible ? 'Open for donations' : availabilityMessage ?? 'Not currently open' }}
                    </span>
                    <span v-if="formatted_target_amount" class="text-lg font-semibold">{{ formatted_target_amount }}</span>
                </div>

                <p v-if="campaign.starts_at || campaign.ends_at" class="mt-2 text-sm text-stone-500">
                    <span v-if="campaign.starts_at">From {{ formatDate(campaign.starts_at) }}</span>
                    <span v-if="campaign.starts_at && campaign.ends_at"> · </span>
                    <span v-if="campaign.ends_at">Until {{ formatDate(campaign.ends_at) }}</span>
                </p>

                <p v-if="campaign.summary" class="mt-6 text-stone-600">{{ campaign.summary }}</p>
                <div v-if="campaign.description_html" class="prose prose-stone mt-6 max-w-none" v-html="campaign.description_html"></div>

                <!-- Donation is IMP-008 (NOT STARTED) — no transaction UI is rendered here.
                     This CTA is display-only and never represents an implemented flow. -->
                <div class="mt-8 rounded-xl border border-dashed border-stone-300 bg-stone-50 p-4 text-center text-sm text-stone-500">
                    Donation is not yet available on this platform.
                </div>
            </div>
        </main>
    </div>
</template>
