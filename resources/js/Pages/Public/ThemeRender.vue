<script setup lang="ts">
import { computed, ref } from 'vue';

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

interface ContentListItem {
    ulid: string;
    title: string;
    summary?: string | null;
    image_url?: string | null;
    url: string | null;
    metadata?: {
        program_name?: string | null;
        formatted_target_amount?: string | null;
        is_donation_eligible?: boolean;
    };
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

// The primary navigation is whichever navigation_menu_slot component is
// configured FIRST across the template's sections — it renders inside the
// site header rather than inline in the section flow, and is skipped when
// the section loop reaches it (no duplicate render). If no menu is
// configured, the header degrades to logo-only (or nothing at all).
interface NavItem {
    label: string;
    url: string;
    children: NavItem[];
}

const primaryNav = computed(() => {
    for (const section of props.template.sections) {
        const slot = section.components.find((c) => c.type === 'navigation_menu_slot');
        if (slot) {
            return { ulid: slot.ulid, items: (slot.props.items as NavItem[] | undefined) ?? [] };
        }
    }
    return null;
});

const mobileNavOpen = ref(false);

function isPrimaryNav(componentUlid: string): boolean {
    return primaryNav.value?.ulid === componentUlid;
}
</script>

<template>
    <div :style="brandStyle" class="min-h-screen bg-[var(--brand-neutral-bg,#fafaf9)] text-[var(--brand-neutral-text,#1c1917)]">
        <header v-if="template.branding.logo_url || primaryNav" class="sticky top-0 z-20 border-b border-stone-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <a href="/" class="flex items-center gap-2">
                    <img v-if="template.branding.logo_url" :src="template.branding.logo_url" alt="Logo" class="h-8" />
                </a>

                <nav v-if="primaryNav && primaryNav.items.length > 0" class="hidden items-center gap-6 text-sm font-medium sm:flex">
                    <div v-for="item in primaryNav.items" :key="item.url" class="group relative">
                        <a :href="item.url" class="text-stone-700 hover:text-[var(--brand-accent,#047857)]">{{ item.label }}</a>
                        <div v-if="item.children.length > 0" class="absolute left-0 top-full hidden min-w-40 rounded-lg border border-stone-200 bg-white py-1.5 shadow-lg group-hover:block">
                            <a v-for="child in item.children" :key="child.url" :href="child.url" class="block px-3 py-1.5 text-sm text-stone-600 hover:bg-stone-50">
                                {{ child.label }}
                            </a>
                        </div>
                    </div>
                </nav>

                <button
                    v-if="primaryNav && primaryNav.items.length > 0"
                    type="button"
                    class="rounded-lg p-2 text-stone-600 hover:bg-stone-100 sm:hidden"
                    aria-label="Toggle navigation"
                    :aria-expanded="mobileNavOpen"
                    @click="mobileNavOpen = !mobileNavOpen"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-5 w-5"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </div>

            <nav v-if="mobileNavOpen && primaryNav" class="border-t border-stone-200 px-4 py-3 sm:hidden">
                <template v-for="item in primaryNav.items" :key="item.url">
                    <a :href="item.url" class="block py-1.5 text-sm font-medium text-stone-700">{{ item.label }}</a>
                    <a v-for="child in item.children" :key="child.url" :href="child.url" class="block py-1 pl-4 text-sm text-stone-500">{{ child.label }}</a>
                </template>
            </nav>
        </header>

        <h1 class="sr-only">{{ pageTitle }}</h1>

        <main>
            <section
                v-for="section in template.sections"
                :key="section.ulid"
                class="px-4 py-12 sm:px-6"
                :class="section.layout_variant === 'stack-on-mobile' ? 'flex flex-col gap-6 sm:flex-row' : ''"
            >
                <div v-for="component in section.components" :key="component.ulid" class="mx-auto w-full max-w-5xl">
                    <div v-if="isPrimaryNav(component.ulid)"></div>

                    <div v-else-if="component.type === 'hero'" class="py-8 text-center sm:py-16">
                        <h2 class="text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">{{ component.props.headline }}</h2>
                        <p v-if="component.props.subheading" class="mx-auto mt-4 max-w-2xl text-lg text-stone-600">{{ component.props.subheading }}</p>
                        <a
                            v-if="component.props.cta_label"
                            href="#"
                            class="mt-6 inline-block rounded-lg bg-[var(--brand-accent,#047857)] px-5 py-2.5 text-sm font-medium text-white"
                        >
                            {{ component.props.cta_label }}
                        </a>
                    </div>

                    <div v-else-if="component.type === 'rich_text'" class="prose prose-stone mx-auto max-w-3xl" v-html="component.props.html"></div>

                    <img
                        v-else-if="component.type === 'image'"
                        :src="component.props.url as string"
                        :alt="component.props.alt as string"
                        class="w-full rounded-xl"
                    />

                    <div v-else-if="component.type === 'cta_button'" class="text-center">
                        <a
                            :href="component.props.url as string"
                            class="inline-block rounded-lg bg-[var(--brand-accent,#047857)] px-5 py-2.5 text-sm font-medium text-white"
                        >
                            {{ component.props.label }}
                        </a>
                    </div>

                    <template v-else-if="component.type === 'content_list'">
                        <div
                            v-if="(component.props as unknown as unknown[]).length > 0"
                            class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <a
                                v-for="item in (component.props as unknown as Array<ContentListItem>)"
                                :key="item.ulid"
                                :href="item.url ?? '#'"
                                class="block overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm transition hover:border-emerald-300 hover:shadow-md"
                            >
                                <div v-if="item.image_url" class="aspect-video w-full overflow-hidden bg-stone-100">
                                    <img :src="item.image_url" :alt="item.title" class="h-full w-full object-cover" />
                                </div>
                                <div class="p-5">
                                    <p v-if="item.metadata?.program_name" class="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent,#047857)]">
                                        {{ item.metadata.program_name }}
                                    </p>
                                    <h3 class="font-medium text-stone-900">{{ item.title }}</h3>
                                    <p v-if="item.summary" class="mt-1 line-clamp-2 text-sm text-stone-600">{{ item.summary }}</p>
                                    <div v-if="item.metadata?.formatted_target_amount || item.metadata?.is_donation_eligible !== undefined" class="mt-3 flex items-center justify-between border-t border-stone-100 pt-3">
                                        <span v-if="item.metadata?.formatted_target_amount" class="text-sm font-medium text-stone-800">
                                            {{ item.metadata.formatted_target_amount }}
                                        </span>
                                        <span
                                            v-if="item.metadata?.is_donation_eligible !== undefined"
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="item.metadata.is_donation_eligible ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                        >
                                            {{ item.metadata.is_donation_eligible ? 'Open' : 'Not currently open' }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <p v-else class="text-center text-sm text-stone-400">Nothing published here yet.</p>
                    </template>

                    <dl v-else-if="component.type === 'stats'" class="grid grid-cols-2 gap-6 sm:grid-cols-4">
                        <div v-for="(stat, i) in (component.props as unknown as Array<{label:string,value:string}>)" :key="i" class="text-center">
                            <dt class="text-sm text-stone-500">{{ stat.label }}</dt>
                            <dd class="text-2xl font-semibold text-stone-900">{{ stat.value }}</dd>
                        </div>
                    </dl>

                    <a
                        v-else-if="component.type === 'banner' && component.props.url"
                        :href="component.props.url as string"
                        class="block rounded-xl bg-emerald-50 p-4 text-center text-sm font-medium text-emerald-800"
                    >
                        {{ component.props.text }}
                    </a>
                    <div v-else-if="component.type === 'banner'" class="rounded-xl bg-stone-100 p-4 text-center text-sm text-stone-700">
                        {{ component.props.text }}
                    </div>

                    <div v-else-if="component.type === 'card_grid'" class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <component
                            :is="card.url ? 'a' : 'div'"
                            v-for="(card, i) in (component.props as unknown as Array<{title:string,text?:string,image_url?:string,url?:string}>)"
                            :key="i"
                            :href="card.url"
                            class="block overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm transition hover:border-emerald-300 hover:shadow-md"
                        >
                            <img v-if="card.image_url" :src="card.image_url" :alt="card.title" class="aspect-video w-full object-cover" />
                            <div class="p-4">
                                <h3 class="font-medium text-stone-900">{{ card.title }}</h3>
                                <p v-if="card.text" class="mt-1 text-sm text-stone-600">{{ card.text }}</p>
                            </div>
                        </component>
                    </div>
                </div>
            </section>

            <p v-if="template.sections.length === 0" class="p-10 text-center text-stone-500">No content configured for this page yet.</p>
        </main>

        <footer v-if="template.branding.logo_url" class="border-t border-stone-200 py-8 text-center text-sm text-stone-400">
            <img :src="template.branding.logo_url" alt="Logo" class="mx-auto h-6 opacity-60" />
            <p class="mt-3">© {{ new Date().getFullYear() }}</p>
        </footer>
    </div>
</template>
