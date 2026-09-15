<script setup lang="ts">
import { computed } from 'vue';

interface PublishedContent {
    ulid: string;
    kind: string;
    title: string;
    excerpt: string | null;
    bodyHtml: string;
    metaTitle: string | null;
    metaDescription: string | null;
    noIndex: boolean;
}

interface ComponentProps {
    [key: string]: unknown;
}

interface RenderedComponent {
    ulid: string;
    type: string;
    props: ComponentProps;
}

interface RenderedSection {
    ulid: string;
    layout_variant: string;
    components: RenderedComponent[];
}

interface Branding {
    color_tokens: Record<string, string>;
    font_family: string;
    logo_url: string | null;
    favicon_url: string | null;
}

interface TemplateRender {
    template_slug: string | null;
    sections: RenderedSection[];
    branding: Branding;
}

const props = defineProps<{
    content: PublishedContent | null;
    template: TemplateRender;
}>();

const pageTitle = computed(() => props.content?.metaTitle ?? props.content?.title ?? 'Modern Digital Philanthropy Platform');

const brandStyle = computed(() => {
    const tokens = props.template.branding.color_tokens ?? {};
    const vars: Record<string, string> = {};
    for (const [key, value] of Object.entries(tokens)) {
        vars[`--brand-${key.replace(/_/g, '-')}`] = value;
    }
    return vars;
});
</script>

<template>
    <div :style="brandStyle" class="min-h-screen bg-[var(--brand-neutral-bg,#fafafa)] text-[var(--brand-neutral-text,#171717)]">
        <header v-if="template.branding.logo_url" class="border-b p-4">
            <img :src="template.branding.logo_url" alt="Logo" class="h-8" />
        </header>

        <h1 class="sr-only">{{ pageTitle }}</h1>

        <main>
            <section
                v-for="section in template.sections"
                :key="section.ulid"
                class="px-4 py-10"
                :class="section.layout_variant === 'stack-on-mobile' ? 'flex flex-col gap-6 sm:flex-row' : ''"
            >
                <div v-for="component in section.components" :key="component.ulid" class="mx-auto max-w-4xl w-full">
                    <div v-if="component.type === 'hero'" class="text-center py-12">
                        <h2 class="text-3xl font-semibold">{{ component.props.headline }}</h2>
                        <p v-if="component.props.subheading" class="mt-2 text-neutral-600">{{ component.props.subheading }}</p>
                    </div>

                    <div v-else-if="component.type === 'rich_text'" class="prose max-w-none" v-html="component.props.html"></div>

                    <img
                        v-else-if="component.type === 'image'"
                        :src="component.props.url as string"
                        :alt="component.props.alt as string"
                        class="w-full rounded"
                    />

                    <a
                        v-else-if="component.type === 'cta_button'"
                        :href="component.props.url as string"
                        class="inline-block rounded bg-[var(--brand-accent,#2563eb)] px-4 py-2 text-white"
                    >
                        {{ component.props.label }}
                    </a>

                    <ul v-else-if="component.type === 'content_list'" class="space-y-2">
                        <li v-for="item in (component.props as unknown as Array<{ulid:string,title:string}>)" :key="item.ulid">
                            {{ item.title }}
                        </li>
                    </ul>

                    <dl v-else-if="component.type === 'stats'" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div v-for="(stat, i) in (component.props as unknown as Array<{label:string,value:string}>)" :key="i" class="text-center">
                            <dt class="text-sm text-neutral-500">{{ stat.label }}</dt>
                            <dd class="text-2xl font-semibold">{{ stat.value }}</dd>
                        </div>
                    </dl>

                    <div v-else-if="component.type === 'banner'" class="rounded bg-neutral-100 p-4 text-center">
                        {{ component.props.text }}
                    </div>

                    <div v-else-if="component.type === 'card_grid'" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div
                            v-for="(card, i) in (component.props as unknown as Array<{title:string,text?:string,image_url?:string,url?:string}>)"
                            :key="i"
                            class="rounded border p-4"
                        >
                            <img v-if="card.image_url" :src="card.image_url" :alt="card.title" class="mb-2 w-full rounded" />
                            <h3 class="font-semibold">{{ card.title }}</h3>
                            <p v-if="card.text" class="text-sm text-neutral-600">{{ card.text }}</p>
                        </div>
                    </div>

                    <nav v-else-if="component.type === 'navigation_menu_slot'" class="flex gap-4 text-sm">
                        <!-- menu items are resolved server-side per menu_code at a
                             future slice; this slot is a placeholder position. -->
                    </nav>
                </div>
            </section>

            <p v-if="template.sections.length === 0" class="p-10 text-center text-neutral-500">
                No content configured for this page yet.
            </p>
        </main>
    </div>
</template>
