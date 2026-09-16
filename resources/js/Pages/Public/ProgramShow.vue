<script setup lang="ts">
import { computed } from 'vue';

interface ProgramProp {
    name: string;
    summary: string | null;
    description_html: string | null;
    cover_image_url: string | null;
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
    <div :style="brandStyle" class="min-h-screen bg-[var(--brand-neutral-bg,#fafaf9)] text-[var(--brand-neutral-text,#1c1917)]">
        <div class="aspect-[21/9] w-full overflow-hidden bg-stone-200 sm:aspect-[3/1]">
            <img v-if="program.cover_image_url" :src="program.cover_image_url" :alt="program.name" class="h-full w-full object-cover" />
        </div>

        <main class="mx-auto -mt-10 max-w-3xl px-4 pb-16 sm:px-6">
            <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
                <h1 class="text-2xl font-semibold sm:text-3xl">{{ program.name }}</h1>
                <p v-if="program.summary" class="mt-3 text-stone-600">{{ program.summary }}</p>
                <div v-if="program.description_html" class="prose prose-stone mt-6 max-w-none" v-html="program.description_html"></div>
            </div>
        </main>
    </div>
</template>
