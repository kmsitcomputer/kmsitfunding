<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Button from '../../UI/Button.vue';
import CmsContentSelect from './CmsContentSelect.vue';
import Input from '../../UI/Input.vue';
import Select from '../../UI/Select.vue';
import Textarea from '../../UI/Textarea.vue';

interface AuthoredCard {
    title: string;
    text?: string | null;
    theme_asset_ulid?: string | null;
    destination_type?: string | null;
    destination_route?: string | null;
    destination_external_url?: string | null;
}

interface StatItem {
    label: string;
    value: string;
}

const props = defineProps<{
    mode: 'add' | 'edit';
    templateUlid: string;
    sectionUlid: string | null;
    blockKey: string;
    blockRegistryKey: string;
    operatorLabel: string;
    initialConfig: Record<string, unknown>;
    expectedUpdatedAt: string | null;
    formErrors: Record<string, string[]>;
    assets: Array<{ ulid: string; original_filename: string | null; mime_type: string | null }>;
    cmsPages: Array<{ ulid: string; title: string | null }>;
    cmsArticles: Array<{ ulid: string; title: string | null }>;
    cmsTitles: Record<string, string>;
    cmsPagesHasMore: boolean;
    cmsArticlesHasMore: boolean;
    cmsContentUrl: string;
}>();

const emit = defineEmits<{ close: [] }>();

const form = reactive<Record<string, unknown>>({ ...(props.initialConfig as Record<string, unknown>) });

// SC-02 (defense in depth) — capture the section identity TOGETHER WITH the
// config and revision token at the same mount-time point, and submit
// against the captured identity only. Parent keying (Show.vue
// `:key="editorKey..."`) already guarantees a fresh instance per target,
// so this second layer only matters if that keying ever regresses — but it
// makes CONFIG-A + TOKEN-A → URL-B structurally impossible either way.
const capturedSectionUlid = props.sectionUlid;

// RA-04 (Codex second-pass finding) — the config being submitted and the
// revision token submitted with it must belong to the SAME loaded snapshot.
// `form` is already frozen at mount time (reactive() copies initialConfig
// once, on `<script setup>` evaluation, and is never re-synced from later
// prop changes). `expectedUpdatedAt` was NOT previously captured the same
// way — submit() read the live `props.expectedUpdatedAt`, which DOES change
// if Inertia re-renders this page with fresh props (e.g. another action
// completing, or another writer's change) while this panel stays open,
// producing exactly the bug: an old config submitted with a brand-new
// token, defeating the whole stale-edit check. Capturing it once here,
// alongside `form`, makes that combination structurally impossible — both
// halves of the snapshot are now equally frozen.
const capturedExpectedUpdatedAt = props.expectedUpdatedAt;

const isHero = computed(() => props.blockKey === 'hero');
const isBanner = computed(() => props.blockKey === 'banner');
const isRichText = computed(() => props.blockKey === 'rich_text');
const isCta = computed(() => props.blockKey === 'cta_button');
const isContentList = computed(() => props.blockKey === 'content_list');
const isStats = computed(() => props.blockKey === 'stats');
const isCardGrid = computed(() => props.blockKey === 'card_grid');

const submitLabel = computed(() => (props.mode === 'add' ? 'Add Block' : 'Save Changes'));

const dismissibleModel = computed({
    get: () => (form.dismissible ? 'yes' : 'no'),
    set: (value: string) => {
        form.dismissible = value === 'yes';
    },
});

const statItems = computed<StatItem[]>(() => {
    const raw = form.items as StatItem[] | undefined;
    if (Array.isArray(raw) && raw.length > 0) return raw;
    return [{ label: '', value: '' }];
});

const setStatItem = (index: number, field: 'label' | 'value', value: string) => {
    const items = [...statItems.value];
    items[index] = { ...items[index], [field]: value };
    form.items = items;
};

const addStatItem = () => {
    if (statItems.value.length >= 8) return;
    form.items = [...statItems.value, { label: '', value: '' }];
};

const removeStatItem = (index: number) => {
    if (statItems.value.length <= 1) return;
    form.items = statItems.value.filter((_, i) => i !== index);
};

const cards = computed<AuthoredCard[]>(() => {
    const raw = form.cards as AuthoredCard[] | undefined;
    return Array.isArray(raw) ? raw : [];
});

const setCard = (index: number, patch: Partial<AuthoredCard>) => {
    const next = [...cards.value];
    next[index] = { ...next[index], ...patch };
    form.cards = next;
};

const addCard = () => {
    if (cards.value.length >= 12) return;
    form.cards = [...cards.value, { title: '', text: null, theme_asset_ulid: null, destination_type: null }];
};

const removeCard = (index: number) => {
    form.cards = cards.value.filter((_, i) => i !== index);
};

const errorText = (field: string): string | null => {
    const messages = props.formErrors[field];
    return messages && messages.length > 0 ? messages[0] : null;
};

const submit = () => {
    const config = JSON.parse(JSON.stringify(form)) as Record<string, string | number | boolean | null | unknown[] | Record<string, unknown>>;
    if (props.mode === 'add') {
        router.post(
            `/admin/page-builder/templates/${props.templateUlid}/blocks`,
            {
                block_key: props.blockRegistryKey,
                config: config as unknown as Record<string, string>,
            },
            // RA-04: close after a successful save rather than leaving a
            // frozen snapshot open against a now-stale server state — the
            // next edit opens fresh and captures a new config+token pair.
            { onSuccess: () => emit('close') },
        );
    } else {
        // SC-02: the URL uses the captured identity — never the live prop —
        // so config + token + target always belong to one snapshot.
        router.patch(
            `/admin/page-builder/blocks/${capturedSectionUlid}`,
            {
                config: config as unknown as Record<string, string>,
                expected_updated_at: capturedExpectedUpdatedAt,
                template_ulid: props.templateUlid,
            },
            { onSuccess: () => emit('close') },
        );
    }
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" role="dialog" aria-modal="true" :aria-label="`Configure ${operatorLabel}`">
        <div class="max-h-[85vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ operatorLabel }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ mode === 'add' ? 'New block' : 'Edit block settings' }} — saved to the draft layout only.</p>
                </div>
                <Button variant="ghost" aria-label="Close configuration panel" @click="emit('close')">Close</Button>
            </div>

            <div v-if="errorText('block_key') || errorText('config') || errorText('section')" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p>{{ errorText('block_key') ?? errorText('config') ?? errorText('section') }}</p>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <template v-if="isHero">
                    <div>
                        <label for="pb-headline" class="mb-1 block text-sm font-medium text-slate-700">Headline</label>
                        <Input id="pb-headline" v-model="form.headline as string" :maxlength="255" />
                    </div>
                    <div>
                        <label for="pb-subheading" class="mb-1 block text-sm font-medium text-slate-700">Subheading</label>
                        <Textarea id="pb-subheading" v-model="form.subheading as string" :rows="3" />
                    </div>
                    <div>
                        <label for="pb-asset" class="mb-1 block text-sm font-medium text-slate-700">Background image</label>
                        <Select id="pb-asset" v-model="form.background_theme_asset_ulid as string">
                            <option :value="null">None</option>
                            <option v-for="asset in assets" :key="asset.ulid" :value="asset.ulid">{{ asset.original_filename ?? asset.ulid }}</option>
                        </Select>
                    </div>
                    <div>
                        <label for="pb-cta" class="mb-1 block text-sm font-medium text-slate-700">Call to action label</label>
                        <Input id="pb-cta" v-model="form.cta_label as string" :maxlength="100" />
                    </div>
                </template>

                <template v-if="isBanner">
                    <div>
                        <label for="pb-text" class="mb-1 block text-sm font-medium text-slate-700">Banner text</label>
                        <Textarea id="pb-text" v-model="form.text as string" :rows="3" />
                    </div>
                    <div>
                        <label for="pb-dismissible" class="mb-1 block text-sm font-medium text-slate-700">Dismissible</label>
                        <Select id="pb-dismissible" v-model="dismissibleModel">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </Select>
                    </div>
                    <div>
                        <label for="pb-banner-dest" class="mb-1 block text-sm font-medium text-slate-700">Link destination</label>
                        <Select id="pb-banner-dest" v-model="form.destination_type as string">
                            <option :value="null">None</option>
                            <option value="SYSTEM_ROUTE">System page</option>
                            <option value="CMS_CONTENT">Published content</option>
                            <option value="EXTERNAL_URL">External link</option>
                        </Select>
                    </div>
                    <div v-if="form.destination_type === 'SYSTEM_ROUTE'">
                        <label for="pb-banner-route" class="mb-1 block text-sm font-medium text-slate-700">Route name</label>
                        <Input id="pb-banner-route" v-model="form.destination_route as string" />
                    </div>
                    <div v-if="form.destination_type === 'EXTERNAL_URL'">
                        <label for="pb-banner-url" class="mb-1 block text-sm font-medium text-slate-700">External URL</label>
                        <Input id="pb-banner-url" v-model="form.destination_external_url as string" placeholder="https://" />
                    </div>
                    <div v-if="form.destination_type === 'CMS_CONTENT'">
                        <CmsContentSelect
                            :kind="(form.destination_content_kind as string) ?? null"
                            :content-ulid="(form.destination_content_ulid as string) ?? null"
                            :pages="cmsPages"
                            :articles="cmsArticles"
                            :titles="cmsTitles"
                            :pages-has-more="cmsPagesHasMore"
                            :articles-has-more="cmsArticlesHasMore"
                            :content-url="cmsContentUrl"
                            kind-field-id="pb-banner-cms-kind"
                            item-field-id="pb-banner-cms-item"
                            :show-kind="true"
                            @update:kind="(v) => (form.destination_content_kind = v)"
                            @update:content-ulid="(v) => (form.destination_content_ulid = v)"
                        />
                    </div>
                </template>

                <template v-if="isRichText">
                    <div>
                        <label for="pb-source" class="mb-1 block text-sm font-medium text-slate-700">Source</label>
                        <Select id="pb-source" v-model="form.source as string">
                            <option value="cms_content">Published page or article</option>
                            <option value="caption">Short text</option>
                            <option value="custom_html">Safe custom content</option>
                        </Select>
                    </div>
                    <div v-if="form.source === 'caption'">
                        <label for="pb-caption" class="mb-1 block text-sm font-medium text-slate-700">Caption</label>
                        <Textarea id="pb-caption" v-model="form.caption as string" :rows="4" />
                    </div>
                    <div v-if="form.source === 'custom_html'">
                        <label for="pb-body" class="mb-1 block text-sm font-medium text-slate-700">Custom content</label>
                        <Textarea id="pb-body" v-model="form.body_html as string" :rows="8" placeholder="Simple paragraphs, headings, links and lists. Scripts and embeds are rejected." />
                        <p v-if="errorText('body_html')" class="mt-1 text-xs text-red-700" role="alert">{{ errorText('body_html') }}</p>
                        <p class="mt-1 text-xs text-slate-500">Sanitized on save — scripts, iframes, and styling are removed or rejected.</p>
                    </div>
                    <div v-if="form.source === 'cms_content'">
                        <CmsContentSelect
                            :kind="(form.content_kind as string) ?? null"
                            :content-ulid="(form.content_ulid as string) ?? null"
                            :pages="cmsPages"
                            :articles="cmsArticles"
                            :titles="cmsTitles"
                            :pages-has-more="cmsPagesHasMore"
                            :articles-has-more="cmsArticlesHasMore"
                            :content-url="cmsContentUrl"
                            kind-field-id="pb-kind"
                            item-field-id="pb-cms-item"
                            :show-kind="true"
                            @update:kind="(v) => (form.content_kind = v)"
                            @update:content-ulid="(v) => (form.content_ulid = v)"
                        />
                    </div>
                </template>

                <template v-if="isCta">
                    <div>
                        <label for="pb-label" class="mb-1 block text-sm font-medium text-slate-700">Button label</label>
                        <Input id="pb-label" v-model="form.label as string" :maxlength="100" />
                    </div>
                    <div>
                        <label for="pb-variant" class="mb-1 block text-sm font-medium text-slate-700">Style</label>
                        <Select id="pb-variant" v-model="form.variant as string">
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                            <option value="outline">Outline</option>
                        </Select>
                    </div>
                    <div>
                        <label for="pb-intent" class="mb-1 block text-sm font-medium text-slate-700">Intent</label>
                        <Select id="pb-intent" v-model="form.intent as string">
                            <option value="general">General</option>
                            <option value="donation">Donation</option>
                            <option value="zakat">Zakat</option>
                        </Select>
                    </div>
                    <div>
                        <label for="pb-dest" class="mb-1 block text-sm font-medium text-slate-700">Destination type</label>
                        <Select id="pb-dest" v-model="form.destination_type as string">
                            <option value="SYSTEM_ROUTE">System page</option>
                            <option value="CMS_CONTENT">Published content</option>
                            <option value="EXTERNAL_URL">External link</option>
                        </Select>
                    </div>
                    <div v-if="form.destination_type === 'SYSTEM_ROUTE'">
                        <label for="pb-route" class="mb-1 block text-sm font-medium text-slate-700">Route name</label>
                        <Input id="pb-route" v-model="form.destination_route as string" />
                    </div>
                    <div v-if="form.destination_type === 'CMS_CONTENT'">
                        <CmsContentSelect
                            :kind="(form.destination_content_kind as string) ?? null"
                            :content-ulid="(form.destination_content_ulid as string) ?? null"
                            :pages="cmsPages"
                            :articles="cmsArticles"
                            :titles="cmsTitles"
                            :pages-has-more="cmsPagesHasMore"
                            :articles-has-more="cmsArticlesHasMore"
                            :content-url="cmsContentUrl"
                            kind-field-id="pb-cms-kind"
                            item-field-id="pb-cms-item"
                            :show-kind="true"
                            @update:kind="(v) => (form.destination_content_kind = v)"
                            @update:content-ulid="(v) => (form.destination_content_ulid = v)"
                        />
                    </div>
                    <div v-if="form.destination_type === 'EXTERNAL_URL'">
                        <label for="pb-url" class="mb-1 block text-sm font-medium text-slate-700">External URL</label>
                        <Input id="pb-url" v-model="form.destination_external_url as string" placeholder="https://" />
                    </div>
                </template>

                <template v-if="isContentList">
                    <div>
                        <label for="pb-ckind" class="mb-1 block text-sm font-medium text-slate-700">Show</label>
                        <Select id="pb-ckind" v-model="form.content_kind as string">
                            <option value="campaign">Campaigns</option>
                            <option value="program">Programs</option>
                            <option value="article">Articles / News</option>
                            <option value="page">Pages</option>
                        </Select>
                    </div>
                    <div v-if="form.content_kind === 'article'">
                        <label for="pb-atype" class="mb-1 block text-sm font-medium text-slate-700">Article type</label>
                        <Select id="pb-atype" v-model="form.article_type as string">
                            <option :value="null">All</option>
                            <option value="ARTICLE">Articles</option>
                            <option value="NEWS">News</option>
                        </Select>
                    </div>
                    <div>
                        <label for="pb-limit" class="mb-1 block text-sm font-medium text-slate-700">Number of items</label>
                        <Input id="pb-limit" v-model="form.limit as number" type="number" min="1" />
                    </div>
                    <div>
                        <label for="pb-order" class="mb-1 block text-sm font-medium text-slate-700">Order</label>
                        <Select id="pb-order" v-model="form.order as string">
                            <option value="latest">Latest first</option>
                            <option value="oldest">Oldest first</option>
                        </Select>
                    </div>
                    <div>
                        <label for="pb-display" class="mb-1 block text-sm font-medium text-slate-700">Display</label>
                        <Select id="pb-display" v-model="form.display_mode as string">
                            <option value="grid">Grid</option>
                            <option value="carousel">Carousel</option>
                        </Select>
                    </div>
                </template>

                <template v-if="isStats">
                    <fieldset>
                        <legend class="mb-1 block text-sm font-medium text-slate-700">Figures</legend>
                        <ul class="space-y-3">
                            <li v-for="(item, index) in statItems" :key="index" class="flex items-end gap-2">
                                <div class="min-w-0 flex-1">
                                    <label :for="`pb-stat-label-${index}`" class="mb-1 block text-xs font-medium text-slate-600">Label</label>
                                    <Input :id="`pb-stat-label-${index}`" :model-value="item.label" :maxlength="100" @update:model-value="(v: string | number | null | undefined) => setStatItem(index, 'label', String(v ?? ''))" />
                                </div>
                                <div class="w-28 shrink-0">
                                    <label :for="`pb-stat-value-${index}`" class="mb-1 block text-xs font-medium text-slate-600">Value</label>
                                    <Input :id="`pb-stat-value-${index}`" :model-value="item.value" :maxlength="50" @update:model-value="(v: string | number | null | undefined) => setStatItem(index, 'value', String(v ?? ''))" />
                                </div>
                                <Button variant="ghost" :disabled="statItems.length <= 1" :aria-label="`Remove figure ${index + 1}`" @click="removeStatItem(index)">Remove</Button>
                            </li>
                        </ul>
                        <Button v-if="statItems.length < 8" variant="secondary" class="mt-3" aria-label="Add figure" @click="addStatItem">Add figure</Button>
                    </fieldset>
                </template>

                <template v-if="isCardGrid">
                    <fieldset>
                        <legend class="mb-1 block text-sm font-medium text-slate-700">Cards</legend>
                        <ul class="space-y-4">
                            <li v-for="(card, index) in cards" :key="index" class="rounded-lg border border-slate-200 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-slate-700">Card {{ index + 1 }}</p>
                                    <Button variant="ghost" :aria-label="`Remove card ${index + 1}`" @click="removeCard(index)">Remove</Button>
                                </div>
                                <div class="mt-2 space-y-3">
                                    <div>
                                        <label :for="`pb-card-title-${index}`" class="mb-1 block text-xs font-medium text-slate-600">Title</label>
                                        <Input :id="`pb-card-title-${index}`" :model-value="card.title" :maxlength="150" @update:model-value="(v: string | number | null | undefined) => setCard(index, { title: String(v ?? '') })" />
                                    </div>
                                    <div>
                                        <label :for="`pb-card-text-${index}`" class="mb-1 block text-xs font-medium text-slate-600">Text</label>
                                        <Textarea :id="`pb-card-text-${index}`" :model-value="card.text ?? null" :rows="2" @update:model-value="(v: string | null | undefined) => setCard(index, { text: v ?? null })" />
                                    </div>
                                    <div>
                                        <label :for="`pb-card-asset-${index}`" class="mb-1 block text-xs font-medium text-slate-600">Image</label>
                                        <Select :id="`pb-card-asset-${index}`" :model-value="card.theme_asset_ulid ?? null" @update:model-value="(v: string | number | null | undefined) => setCard(index, { theme_asset_ulid: v == null || v === '' ? null : String(v) })">
                                            <option :value="null">None</option>
                                            <option v-for="asset in assets" :key="asset.ulid" :value="asset.ulid">{{ asset.original_filename ?? asset.ulid }}</option>
                                        </Select>
                                    </div>
                                    <div>
                                        <label :for="`pb-card-dest-${index}`" class="mb-1 block text-xs font-medium text-slate-600">Link</label>
                                        <Select :id="`pb-card-dest-${index}`" :model-value="card.destination_type ?? null" @update:model-value="(v: string | number | null | undefined) => setCard(index, { destination_type: v == null || v === '' ? null : String(v) })">
                                            <option :value="null">None</option>
                                            <option value="SYSTEM_ROUTE">System page</option>
                                            <option value="EXTERNAL_URL">External link</option>
                                        </Select>
                                    </div>
                                    <div v-if="card.destination_type === 'SYSTEM_ROUTE'">
                                        <label :for="`pb-card-route-${index}`" class="mb-1 block text-xs font-medium text-slate-600">Route name</label>
                                        <Input :id="`pb-card-route-${index}`" :model-value="card.destination_route ?? null" @update:model-value="(v: string | number | null | undefined) => setCard(index, { destination_route: v == null ? null : String(v) })" />
                                    </div>
                                    <div v-if="card.destination_type === 'EXTERNAL_URL'">
                                        <label :for="`pb-card-url-${index}`" class="mb-1 block text-xs font-medium text-slate-600">External URL</label>
                                        <Input :id="`pb-card-url-${index}`" :model-value="card.destination_external_url ?? null" placeholder="https://" @update:model-value="(v: string | number | null | undefined) => setCard(index, { destination_external_url: v == null ? null : String(v) })" />
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <Button v-if="cards.length < 12" variant="secondary" class="mt-3" aria-label="Add card" @click="addCard">Add card</Button>
                    </fieldset>
                </template>

                <div class="flex justify-end gap-2 pt-2">
                    <Button variant="secondary" @click="emit('close')">Cancel</Button>
                    <Button type="submit">{{ submitLabel }}</Button>
                </div>
            </form>
        </div>
    </div>
</template>
