<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../Components/UI/Breadcrumb.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import FormField from '../../Components/UI/FormField.vue';
import Input from '../../Components/UI/Input.vue';
import PageHeader from '../../Components/UI/PageHeader.vue';
import Select from '../../Components/UI/Select.vue';
import StatusBadge from '../../Components/UI/StatusBadge.vue';
import Textarea from '../../Components/UI/Textarea.vue';

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
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Theme', href: '/admin/theme' }, { label: theme.name }]" />
        </template>
        <template #header>
            <PageHeader :title="theme.name" description="Presentation configuration — templates, sections, navigation, branding, assets.">
                <template #badge>
                    <StatusBadge :status="theme.status" />
                </template>
                <template #actions>
                    <Button v-if="theme.status !== 'ACTIVE'" @click="activate">Activate</Button>
                </template>
            </PageHeader>
        </template>

        <div class="space-y-6">
            <Card>
                <h2 class="text-sm font-semibold text-slate-800">Templates</h2>

                <div class="mt-4 space-y-4">
                    <div v-for="template in templates" :key="template.ulid" class="rounded-lg border border-slate-200 p-4">
                        <p class="font-medium text-slate-800">{{ template.name }} <span class="text-slate-400">({{ template.content_kind }})</span></p>

                        <div v-for="section in template.sections" :key="section.ulid" class="ml-3 mt-3 rounded-md border-l-2 border-slate-200 pl-3">
                            <p class="text-sm text-slate-600">Section — {{ section.layout_variant }}</p>
                            <ul class="ml-4 list-disc text-sm text-slate-600">
                                <li v-for="component in section.components" :key="component.ulid">{{ component.type }}</li>
                            </ul>

                            <form class="mt-2 flex flex-wrap items-end gap-2" @submit.prevent="submitComponent(section.ulid)">
                                <Select v-model="componentForm.type" class="!w-auto">
                                    <option value="hero">hero</option>
                                    <option value="rich_text">rich_text</option>
                                    <option value="image">image</option>
                                    <option value="cta_button">cta_button</option>
                                    <option value="content_list">content_list</option>
                                    <option value="stats">stats</option>
                                    <option value="banner">banner</option>
                                    <option value="card_grid">card_grid</option>
                                    <option value="navigation_menu_slot">navigation_menu_slot</option>
                                </Select>
                                <Textarea v-model="componentForm.config" :rows="1" class="flex-1 font-mono !text-xs" placeholder="{}" />
                                <Button type="submit" variant="secondary">Add component</Button>
                            </form>
                            <p v-if="componentForm.errors.config" class="mt-1 text-xs text-red-600">{{ componentForm.errors.config }}</p>
                        </div>

                        <form class="mt-3 flex items-end gap-2" @submit.prevent="submitSection(template.ulid)">
                            <Input v-model="sectionForm.layout_variant" placeholder="layout_variant" class="!w-auto" />
                            <Button type="submit" variant="secondary">Add section</Button>
                        </form>
                    </div>
                </div>

                <form class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4" @submit.prevent="submitTemplate">
                    <Input v-model="templateForm.name" placeholder="Template name" class="!w-auto" />
                    <Select v-model="templateForm.content_kind" class="!w-auto">
                        <option value="home">home</option>
                        <option value="page">page</option>
                        <option value="article">article</option>
                    </Select>
                    <Button type="submit" variant="secondary">Add template</Button>
                </form>
                <p v-if="templateForm.errors.content_kind" class="mt-1 text-xs text-red-600">{{ templateForm.errors.content_kind }}</p>
            </Card>

            <Card>
                <h2 class="text-sm font-semibold text-slate-800">Navigation menus</h2>
                <ul v-if="navigationMenus.length > 0" class="mt-3 space-y-1.5 text-sm text-slate-600">
                    <li v-for="menu in navigationMenus" :key="menu.ulid">{{ menu.name }} ({{ menu.code }}) — {{ menu.items.length }} item(s)</li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">No navigation menus yet.</p>
                <form class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4" @submit.prevent="submitMenu">
                    <Input v-model="menuForm.code" placeholder="code (e.g. primary)" class="!w-auto" />
                    <Input v-model="menuForm.name" placeholder="Name" class="!w-auto" />
                    <Button type="submit" variant="secondary">Add menu</Button>
                </form>
            </Card>

            <Card>
                <h2 class="text-sm font-semibold text-slate-800">Branding</h2>
                <form class="mt-3 space-y-3" @submit.prevent="submitBranding">
                    <FormField label="Color tokens (JSON)" :error="brandingForm.errors.color_tokens">
                        <Textarea v-model="brandingForm.color_tokens" :rows="6" class="font-mono !text-xs" />
                    </FormField>
                    <FormField label="Font family">
                        <Select v-model="brandingForm.font_family" class="!w-auto">
                            <option value="system">system</option>
                            <option value="serif">serif</option>
                            <option value="mono">mono</option>
                        </Select>
                    </FormField>
                    <Button type="submit">Save branding</Button>
                </form>
            </Card>

            <Card>
                <h2 class="text-sm font-semibold text-slate-800">Theme assets</h2>
                <ul v-if="assets.length > 0" class="mt-3 space-y-1 text-sm text-slate-600">
                    <li v-for="asset in assets" :key="asset.ulid">{{ asset.original_filename }}</li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">No assets uploaded yet.</p>
                <form class="mt-4 flex items-end gap-2 border-t border-slate-100 pt-4" @submit.prevent="uploadAsset">
                    <input type="file" class="text-sm" @change="onFileChange" />
                    <Button type="submit" variant="secondary">Upload</Button>
                </form>
                <p v-if="assetForm.errors.file" class="mt-1 text-xs text-red-600">{{ assetForm.errors.file }}</p>
            </Card>
        </div>
    </AdminLayout>
</template>
