<script setup lang="ts">
import { computed } from 'vue';

interface ProgramProp {
    name: string;
    summary: string | null;
    description_html: string | null;
}

interface Branding {
    color_tokens: Record<string, string>;
    font_family: string;
}

const props = defineProps<{
    program: ProgramProp;
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
</script>

<template>
    <div :style="brandStyle" class="min-h-screen bg-[var(--brand-neutral-bg,#fafafa)] text-[var(--brand-neutral-text,#171717)]">
        <main class="mx-auto max-w-3xl px-4 py-12">
            <h1 class="text-3xl font-semibold">{{ program.name }}</h1>
            <p v-if="program.summary" class="mt-2 text-neutral-600">{{ program.summary }}</p>
            <div v-if="program.description_html" class="prose mt-6 max-w-none" v-html="program.description_html"></div>
        </main>
    </div>
</template>
