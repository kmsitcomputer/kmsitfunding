<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

interface ComponentRow {
    ulid: string;
    type: string;
    config: Record<string, unknown>;
    position: number;
}

interface SectionRow {
    ulid: string;
    is_reusable: boolean;
    layout_variant: string;
    visible: boolean;
    components: ComponentRow[];
}

interface TemplateRow {
    ulid: string;
    slug: string;
    name: string;
    content_kind: string;
    sections: SectionRow[];
}

interface NavigationItemRow {
    ulid: string;
    label: string;
    destination_type: string;
}

interface NavigationMenuRow {
    ulid: string;
    code: string;
    name: string;
    items: NavigationItemRow[];
}

interface AssetRow {
    ulid: string;
    original_filename: string;
    mime_type: string;
}

interface ThemeRow {
    ulid: string;
    name: string;
    status: string;
    is_system_default: boolean;
}

const props = defineProps<{
    theme: ThemeRow;
    templates: TemplateRow[];
    navigationMenus: NavigationMenuRow[];
    branding: { color_tokens: Record<string, string>; font_family: string } | null;
    assets: AssetRow[];
}>();

const activateForm = useForm({});
const activate = () => activateForm.post(`/admin/theme/${props.theme.ulid}/activate`);

const templateForm = useForm({ name: '', content_kind: 'page', slug: '' });
const submitTemplate = () => templateForm.post(`/admin/theme/${props.theme.ulid}/templates`, { onSuccess: () => templateForm.reset() });

const sectionForm = useForm({ layout_variant: 'default' });
const submitSection = (templateUlid: string) => sectionForm.post(`/admin/theme/templates/${templateUlid}/sections`, { onSuccess: () => sectionForm.reset() });

const componentForm = useForm({ type: 'hero', config: '{}' });
const submitComponent = (sectionUlid: string) => {
    componentForm.transform((data) => ({ ...data, config: JSON.parse(data.config || '{}') })).post(`/admin/theme/sections/${sectionUlid}/components`, {
        onSuccess: () => componentForm.reset(),
    });
};

const menuForm = useForm({ code: '', name: '' });
const submitMenu = () => menuForm.post(`/admin/theme/${props.theme.ulid}/navigation-menus`, { onSuccess: () => menuForm.reset() });

const brandingForm = useForm({
    color_tokens: JSON.stringify(props.branding?.color_tokens ?? { primary: '#1f2937', secondary: '#4b5563', accent: '#2563eb', neutral_bg: '#fafafa', neutral_text: '#171717' }, null, 2),
    font_family: props.branding?.font_family ?? 'system',
});
const submitBranding = () => {
    brandingForm.transform((data) => ({ ...data, color_tokens: JSON.parse(data.color_tokens) })).post(`/admin/theme/${props.theme.ulid}/branding`);
};

const assetForm = useForm({ file: null as File | null });
const onFileChange = (e: Event) => {
    assetForm.file = (e.target as HTMLInputElement).files?.[0] ?? null;
};
const uploadAsset = () => assetForm.post(`/admin/theme/${props.theme.ulid}/assets`, { forceFormData: true, onSuccess: () => assetForm.reset() });
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-4xl space-y-8">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-neutral-800">{{ theme.name }}</h1>
                <div class="flex items-center gap-2">
                    <span class="rounded border px-2 py-1 text-xs text-neutral-600">{{ theme.status }}</span>
                    <button
                        v-if="theme.status !== 'ACTIVE'"
                        type="button"
                        class="rounded bg-neutral-800 px-3 py-2 text-sm text-white"
                        @click="activate"
                    >
                        Activate
                    </button>
                </div>
            </div>

            <section class="space-y-4 rounded border bg-white p-4">
                <h2 class="text-sm font-semibold text-neutral-600">Templates</h2>

                <div v-for="template in templates" :key="template.ulid" class="rounded border p-3">
                    <p class="font-medium text-neutral-800">{{ template.name }} ({{ template.content_kind }})</p>

                    <div v-for="section in template.sections" :key="section.ulid" class="ml-4 mt-2 rounded border-l-2 border-neutral-200 pl-3">
                        <p class="text-sm text-neutral-600">Section — {{ section.layout_variant }}</p>
                        <ul class="ml-4 list-disc text-sm text-neutral-700">
                            <li v-for="component in section.components" :key="component.ulid">{{ component.type }}</li>
                        </ul>

                        <form class="mt-2 flex items-end gap-2" @submit.prevent="submitComponent(section.ulid)">
                            <select v-model="componentForm.type" class="rounded border px-2 py-1 text-sm">
                                <option value="hero">hero</option>
                                <option value="rich_text">rich_text</option>
                                <option value="image">image</option>
                                <option value="cta_button">cta_button</option>
                                <option value="content_list">content_list</option>
                                <option value="stats">stats</option>
                                <option value="banner">banner</option>
                                <option value="card_grid">card_grid</option>
                                <option value="navigation_menu_slot">navigation_menu_slot</option>
                            </select>
                            <textarea v-model="componentForm.config" rows="2" class="flex-1 rounded border px-2 py-1 font-mono text-xs" placeholder="{}"></textarea>
                            <button type="submit" class="rounded border px-2 py-1 text-xs">Add component</button>
                        </form>
                        <p v-if="componentForm.errors.config" class="text-xs text-red-600">{{ componentForm.errors.config }}</p>
                    </div>

                    <form class="mt-2 flex items-end gap-2" @submit.prevent="submitSection(template.ulid)">
                        <input v-model="sectionForm.layout_variant" type="text" class="rounded border px-2 py-1 text-sm" placeholder="layout_variant" />
                        <button type="submit" class="rounded border px-2 py-1 text-xs">Add section</button>
                    </form>
                </div>

                <form class="flex items-end gap-2" @submit.prevent="submitTemplate">
                    <input v-model="templateForm.name" type="text" class="rounded border px-2 py-1 text-sm" placeholder="Template name" required />
                    <select v-model="templateForm.content_kind" class="rounded border px-2 py-1 text-sm">
                        <option value="home">home</option>
                        <option value="page">page</option>
                        <option value="article">article</option>
                    </select>
                    <button type="submit" class="rounded border px-2 py-1 text-xs">Add template</button>
                </form>
                <p v-if="templateForm.errors.content_kind" class="text-xs text-red-600">{{ templateForm.errors.content_kind }}</p>
            </section>

            <section class="space-y-4 rounded border bg-white p-4">
                <h2 class="text-sm font-semibold text-neutral-600">Navigation Menus</h2>
                <ul>
                    <li v-for="menu in navigationMenus" :key="menu.ulid" class="text-sm text-neutral-700">
                        {{ menu.name }} ({{ menu.code }}) — {{ menu.items.length }} item(s)
                    </li>
                </ul>
                <form class="flex items-end gap-2" @submit.prevent="submitMenu">
                    <input v-model="menuForm.code" type="text" class="rounded border px-2 py-1 text-sm" placeholder="code (e.g. primary)" required />
                    <input v-model="menuForm.name" type="text" class="rounded border px-2 py-1 text-sm" placeholder="Name" required />
                    <button type="submit" class="rounded border px-2 py-1 text-xs">Add menu</button>
                </form>
            </section>

            <section class="space-y-4 rounded border bg-white p-4">
                <h2 class="text-sm font-semibold text-neutral-600">Branding</h2>
                <form class="space-y-2" @submit.prevent="submitBranding">
                    <label class="block text-sm text-neutral-600">Color tokens (JSON)</label>
                    <textarea v-model="brandingForm.color_tokens" rows="6" class="w-full rounded border px-2 py-1 font-mono text-xs"></textarea>
                    <p v-if="brandingForm.errors.color_tokens" class="text-xs text-red-600">{{ brandingForm.errors.color_tokens }}</p>
                    <select v-model="brandingForm.font_family" class="rounded border px-2 py-1 text-sm">
                        <option value="system">system</option>
                        <option value="serif">serif</option>
                        <option value="mono">mono</option>
                    </select>
                    <button type="submit" class="rounded border px-2 py-1 text-xs">Save branding</button>
                </form>
            </section>

            <section class="space-y-4 rounded border bg-white p-4">
                <h2 class="text-sm font-semibold text-neutral-600">Theme Assets</h2>
                <ul class="text-sm text-neutral-700">
                    <li v-for="asset in assets" :key="asset.ulid">{{ asset.original_filename }}</li>
                </ul>
                <form class="flex items-end gap-2" @submit.prevent="uploadAsset">
                    <input type="file" @change="onFileChange" />
                    <button type="submit" class="rounded border px-2 py-1 text-xs">Upload</button>
                </form>
                <p v-if="assetForm.errors.file" class="text-xs text-red-600">{{ assetForm.errors.file }}</p>
            </section>
        </div>
    </div>
</template>
