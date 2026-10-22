<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Button from '../../../Components/UI/Button.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import Icon from '../../../Components/UI/Icon.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import Select from '../../../Components/UI/Select.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';
import BlockConfigPanel from '../../../Components/Admin/PageBuilder/BlockConfigPanel.vue';

interface BlockRow {
    ulid: string;
    visible: boolean;
    isReusable: boolean;
    isTechnical: boolean;
    type: string | null;
    config: Record<string, unknown> | null;
    operatorLabel: string;
    canConfigure: boolean;
    canDuplicate: boolean;
    canRemove: boolean;
    updatedAt: string | null;
    sectionUpdatedAt: string | null;
}

interface LibraryEntry {
    registryKey: string;
    key: string;
    operator_label: string;
    description: string;
    category: string;
    icon: string;
    default_config: Record<string, unknown>;
}

interface ReusableEntry {
    sectionUlid: string;
    operatorLabel: string;
}

interface ContentOption {
    ulid: string;
    title: string | null;
}

interface AssetRow {
    ulid: string;
    original_filename: string | null;
    mime_type: string | null;
}

interface ThemeRow {
    ulid: string;
    name: string;
    status: string;
}

interface TemplateRow {
    ulid: string;
    content_kind: string;
}

const props = defineProps<{
    theme: ThemeRow;
    template: TemplateRow;
    canvasLabel: string;
    contentKind: string;
    blocks: BlockRow[];
    blockLibrary: Array<LibraryEntry & { registryKey?: string }>;
    reusableBlocks: ReusableEntry[];
    assets: AssetRow[];
    cmsPages: ContentOption[];
    cmsArticles: ContentOption[];
    cmsTitles: Record<string, string>;
    cmsPagesHasMore: boolean;
    cmsArticlesHasMore: boolean;
    cmsContentUrl: string;
    advancedUrl: string;
    errors: Record<string, string[]>;
    isDraft: boolean;
    canUpdate: boolean;
    previewUrl: string;
    publishUrl: string;
}>();

const canMutate = computed(() => props.isDraft && props.canUpdate);
const showPicker = ref(false);
const showReusablePicker = ref(false);
const selectedReusableUlid = ref<string>('');
const activeCategory = ref<string>('All');
const editingUlid = ref<string | null>(null);
const addingKey = ref<string | null>(null);
const page = usePage();
const flash = computed(() => (page.props as Record<string, unknown>).flash as Record<string, string> | undefined);
const pageErrors = computed(() => (page.props.errors as Record<string, string> | undefined) ?? {});
const passedErrors = computed(() => props.errors ?? {});

const errorFor = (field: string): string | null => pageErrors.value[field] ?? passedErrors.value[field]?.[0] ?? null;

const categories = computed(() => ['All', ...Array.from(new Set(props.blockLibrary.map((b) => b.category)))]);
const filteredLibrary = computed(() =>
    activeCategory.value === 'All' ? props.blockLibrary : props.blockLibrary.filter((b) => b.category === activeCategory.value),
);
const editingBlock = computed(() => props.blocks.find((b) => b.ulid === editingUlid.value) ?? null);
const addingEntry = computed(() => props.blockLibrary.find((b) => b.registryKey === addingKey.value) ?? null);

// SC-02 (modal background guard, defense in depth behind the snapshot
// identity fix which remains authoritative): while any editor/picker modal
// is open, background block actions are disabled so keyboard navigation
// cannot retarget the open editor's context. No modal framework added.
const modalOpen = computed(() => showPicker.value || showReusablePicker.value || editingUlid.value !== null || addingKey.value !== null);

const moveBlock = (index: number, direction: -1 | 1) => {
    const ordered = props.blocks.map((b) => b.ulid);
    const target = index + direction;
    if (target < 0 || target >= ordered.length) return;
    [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
    router.put(`/admin/page-builder/templates/${props.template.ulid}/blocks/order`, { ordered_section_ulids: ordered });
};

const toggleVisibility = (block: BlockRow) => {
    router.patch(`/admin/page-builder/blocks/${block.ulid}/visibility`, {
        visible: !block.visible,
        expected_updated_at: block.sectionUpdatedAt,
        template_ulid: props.template.ulid,
    });
};

const removeBlock = (block: BlockRow) => {
    if (!window.confirm(`Remove "${block.operatorLabel}" from this layout? This cannot be undone.`)) return;
    router.delete(`/admin/page-builder/templates/${props.template.ulid}/blocks/${block.ulid}`, {
        data: {
            expected_section_updated_at: block.sectionUpdatedAt,
            expected_component_updated_at: block.updatedAt,
        },
    });
};

const duplicateBlock = (block: BlockRow) => {
    if (!window.confirm(`Duplicate "${block.operatorLabel}" immediately after itself?`)) return;
    router.post(`/admin/page-builder/templates/${props.template.ulid}/blocks/${block.ulid}/duplicate`);
};

const placeReusable = () => {
    if (!selectedReusableUlid.value) return;
    router.post(`/admin/page-builder/templates/${props.template.ulid}/blocks/place-reusable`, {
        section_ulid: selectedReusableUlid.value,
    });
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb
                :items="[
                    { label: 'Site Design', href: '/admin/site-design' },
                    { label: 'Page Builder', href: `/admin/page-builder/${theme.ulid}` },
                    { label: canvasLabel },
                ]"
            />
        </template>
        <template #header>
            <PageHeader :title="canvasLabel" description="One shared layout — every page of this type shows these blocks in this order.">
                <template #badge>
                    <StatusBadge :status="theme.status" />
                </template>
                <template #actions>
                    <Button v-if="isDraft" as="a" variant="secondary" :href="previewUrl">Preview</Button>
                    <Button v-if="isDraft && canUpdate" @click="showPicker = true">Add Block</Button>
                    <Button v-if="isDraft && canUpdate && reusableBlocks.length > 0" variant="secondary" @click="showReusablePicker = true">Add Existing Reusable Block</Button>
                </template>
            </PageHeader>
        </template>

        <div class="space-y-6">
            <p v-if="flash?.status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                {{ flash.status }}
            </p>
            <div v-if="errorFor('block_key') || errorFor('ordered_section_ulids') || errorFor('section') || errorFor('section_ulid')" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p>{{ errorFor('block_key') ?? errorFor('ordered_section_ulids') ?? errorFor('section') ?? errorFor('section_ulid') }}</p>
            </div>
            <p v-if="!isDraft" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                This design is {{ theme.status }} and read-only. Clone it to a draft from Site Design to edit blocks.
            </p>

            <Card :padded="false">
                <EmptyState v-if="blocks.length === 0" icon="plus" title="No blocks yet" description="Add the first block to this layout.">
                    <template v-if="canMutate" #action>
                        <Button @click="showPicker = true">Add Block</Button>
                    </template>
                </EmptyState>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="(block, index) in blocks" :key="block.ulid" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-3">
                            <Icon :name="block.isTechnical ? 'settings' : 'document'" class="h-5 w-5 shrink-0 text-slate-400" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ block.operatorLabel }}</p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <span v-if="!block.visible" class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">Disabled</span>
                                    <span v-else class="rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700">Enabled</span>
                                    <span v-if="block.isTechnical">Edit in Advanced/Debug</span>
                                    <span v-if="block.isReusable">Reusable</span>
                                </p>
                                <p v-if="block.isTechnical" class="mt-1">
                                    <a :href="advancedUrl" class="text-xs font-medium text-emerald-700 hover:text-emerald-800">Open in Advanced/Debug</a>
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <Button
                                variant="secondary"
                                :disabled="!canMutate || modalOpen || index === 0"
                                :aria-label="`Move ${block.operatorLabel} up`"
                                @click="moveBlock(index, -1)"
                            >
                                Up
                            </Button>
                            <Button
                                variant="secondary"
                                :disabled="!canMutate || modalOpen || index === blocks.length - 1"
                                :aria-label="`Move ${block.operatorLabel} down`"
                                @click="moveBlock(index, 1)"
                            >
                                Down
                            </Button>
                            <Button
                                variant="secondary"
                                :disabled="!canMutate || modalOpen"
                                :aria-pressed="String(block.visible)"
                                :aria-label="`Toggle ${block.operatorLabel} visibility`"
                                @click="toggleVisibility(block)"
                            >
                                {{ block.visible ? 'Disable' : 'Enable' }}
                            </Button>
                            <Button
                                v-if="block.canConfigure"
                                variant="secondary"
                                :disabled="!canMutate || modalOpen"
                                :aria-label="`Configure ${block.operatorLabel}`"
                                @click="editingUlid = block.ulid"
                            >
                                Configure
                            </Button>
                            <Button
                                v-if="block.canDuplicate"
                                variant="secondary"
                                :disabled="!canMutate || modalOpen"
                                :aria-label="`Duplicate ${block.operatorLabel}`"
                                @click="duplicateBlock(block)"
                            >
                                Duplicate
                            </Button>
                            <Button
                                v-if="block.canRemove"
                                variant="danger"
                                :disabled="!canMutate || modalOpen"
                                :aria-label="`Remove ${block.operatorLabel}`"
                                @click="removeBlock(block)"
                            >
                                Remove
                            </Button>
                        </div>
                    </li>
                </ul>
            </Card>

            <div v-if="showPicker" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" role="dialog" aria-modal="true" aria-label="Add block">
                <div class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-900">Add Block</h2>
                        <Button variant="ghost" aria-label="Close block picker" @click="showPicker = false">Close</Button>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Button
                            v-for="category in categories"
                            :key="category"
                            :variant="activeCategory === category ? 'primary' : 'secondary'"
                            :aria-pressed="String(activeCategory === category)"
                            @click="activeCategory = category"
                        >
                            {{ category }}
                        </Button>
                    </div>
                    <ul class="mt-4 divide-y divide-slate-100">
                        <li v-for="entry in filteredLibrary" :key="entry.registryKey" class="flex items-center justify-between gap-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <Icon :name="entry.icon" class="h-5 w-5 shrink-0 text-slate-400" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-800">{{ entry.operator_label }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ entry.description }}</p>
                                </div>
                            </div>
                            <Button
                                variant="secondary"
                                :disabled="!canMutate"
                                :aria-label="`Add ${entry.operator_label}`"
                                @click="addingKey = entry.registryKey"
                            >
                                Select
                            </Button>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-if="showReusablePicker" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" role="dialog" aria-modal="true" aria-label="Add existing reusable block">
                <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-900">Add Existing Reusable Block</h2>
                        <Button variant="ghost" aria-label="Close reusable picker" @click="showReusablePicker = false">Close</Button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Only reusable blocks from this same design are listed.</p>
                    <div class="mt-4">
                        <label for="pb-reusable" class="mb-1 block text-sm font-medium text-slate-700">Reusable block</label>
                        <Select id="pb-reusable" v-model="selectedReusableUlid">
                            <option value="">Select a block</option>
                            <option v-for="entry in reusableBlocks" :key="entry.sectionUlid" :value="entry.sectionUlid">{{ entry.operatorLabel }}</option>
                        </Select>
                    </div>
                    <p v-if="errorFor('section_ulid')" class="mt-2 text-xs text-red-700" role="alert">{{ errorFor('section_ulid') }}</p>
                    <div class="mt-4 flex justify-end gap-2">
                        <Button variant="secondary" @click="showReusablePicker = false">Cancel</Button>
                        <Button :disabled="!selectedReusableUlid" @click="placeReusable">Place block</Button>
                    </div>
                </div>
            </div>

            <!-- SC-02: key by block identity ONLY (never by timestamp) — a
                 target switch A→B destroys the old instance and mounts a
                 fresh B snapshot, while a same-block prop refresh keeps the
                 mounted instance with its frozen RA-04 snapshot intact. -->
            <BlockConfigPanel
                v-if="editingBlock"
                :key="`edit:${editingBlock.ulid}`"
                mode="edit"
                :template-ulid="template.ulid"
                :section-ulid="editingBlock.ulid"
                :block-key="editingBlock.type ?? ''"
                :block-registry-key="editingBlock.type ?? ''"
                :operator-label="editingBlock.operatorLabel"
                :initial-config="editingBlock.config ?? {}"
                :expected-updated-at="editingBlock.updatedAt"
                :form-errors="passedErrors"
                :assets="assets"
                :cms-pages="cmsPages"
                :cms-articles="cmsArticles"
                :cms-titles="cmsTitles"
                :cms-pages-has-more="cmsPagesHasMore"
                :cms-articles-has-more="cmsArticlesHasMore"
                :cms-content-url="cmsContentUrl"
                @close="editingUlid = null"
            />

            <!-- SC-02 (Add lifecycle): key by registry entry so switching
                 Hero→Banner destroys the Hero form state instead of reusing
                 the instance with stale configuration. -->
            <BlockConfigPanel
                v-if="addingEntry"
                :key="`add:${addingEntry.registryKey}`"
                mode="add"
                :template-ulid="template.ulid"
                :section-ulid="null"
                :block-key="addingEntry.key"
                :block-registry-key="addingEntry.registryKey"
                :operator-label="addingEntry.operator_label"
                :initial-config="addingEntry.default_config"
                :expected-updated-at="null"
                :form-errors="passedErrors"
                :assets="assets"
                :cms-pages="cmsPages"
                :cms-articles="cmsArticles"
                :cms-titles="cmsTitles"
                :cms-pages-has-more="cmsPagesHasMore"
                :cms-articles-has-more="cmsArticlesHasMore"
                :cms-content-url="cmsContentUrl"
                @close="addingKey = null"
            />

            <div class="flex flex-wrap items-center gap-3 text-sm">
                <Link :href="`/admin/page-builder/${theme.ulid}`" class="font-medium text-emerald-700 hover:text-emerald-800">Back to layouts</Link>
                <span class="text-slate-300">·</span>
                <Link :href="previewUrl" class="font-medium text-emerald-700 hover:text-emerald-800">Preview draft</Link>
            </div>
        </div>
    </AdminLayout>
</template>
