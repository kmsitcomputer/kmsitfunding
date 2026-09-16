<script setup lang="ts">
import { computed } from 'vue';

interface CampaignProp {
    name: string;
    summary: string | null;
    description_html: string | null;
    purpose: string | null;
    starts_at: string | null;
    ends_at: string | null;
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

const availabilityMessage = computed(() => {
    if (props.is_donation_eligible) {
        return null;
    }
    if (props.campaign.starts_at) {
        return `This campaign opens on ${props.campaign.starts_at}.`;
    }
    if (props.campaign.ends_at) {
        return `This campaign ended on ${props.campaign.ends_at}.`;
    }
    return null;
});
</script>

<template>
    <div :style="brandStyle" class="min-h-screen bg-[var(--brand-neutral-bg,#fafafa)] text-[var(--brand-neutral-text,#171717)]">
        <main class="mx-auto max-w-3xl px-4 py-12">
            <h1 class="text-3xl font-semibold">{{ campaign.name }}</h1>
            <p v-if="campaign.purpose" class="mt-1 text-neutral-500">{{ campaign.purpose }}</p>
            <p v-if="formatted_target_amount" class="mt-2 text-lg font-medium">Target: {{ formatted_target_amount }}</p>

            <p v-if="availabilityMessage" class="mt-2 rounded bg-amber-50 px-3 py-2 text-sm text-amber-700">
                {{ availabilityMessage }}
            </p>

            <p v-if="campaign.summary" class="mt-4 text-neutral-600">{{ campaign.summary }}</p>
            <div v-if="campaign.description_html" class="prose mt-6 max-w-none" v-html="campaign.description_html"></div>
        </main>
    </div>
</template>
