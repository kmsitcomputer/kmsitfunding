<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface ThemeRow {
    ulid: string;
    name: string;
    slug: string;
    status: string;
}

interface ComponentRow {
    ulid: string;
    type: string;
}

interface SectionRow {
    ulid: string;
    layout_variant: string;
    components: ComponentRow[];
}

interface TemplateRow {
    ulid: string;
    name: string;
    content_kind: string;
    sections: SectionRow[];
}

interface NavItem {
    ulid: string;
    label: string;
    destination_type: string;
}

interface NavMenu {
    ulid: string;
    code: string;
    name: string;
    items: NavItem[];
}

defineProps<{
    theme: ThemeRow;
    templates: TemplateRow[];
    navigationMenus: NavMenu[];
    branding: Record<string, unknown> | null;
    canPublish: boolean;
}>();

const publishTheme = (ulid: string) => {
    router.post(`/admin/site-design/${ulid}/publish`);
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Site Design', href: '/admin/site-design' }, { label: 'Preview' }]" />
        </template>
        <template #header>
            <PageHeader :title="`Preview — draft`" description="Authorized draft structure baseline. Full draft rendering integration belongs to CR-001-E." />
        </template>

        <div class="space-y-6">
            <Card>
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h2 class="text-sm font-semibold text-slate-800">{{ theme.name }}</h2>
                        <StatusBadge :status="theme.status" />
                    </div>
                    <Button v-if="canPublish" :disabled="false" @click="publishTheme(theme.ulid)">Publish design</Button>
                </div>
                <p class="mt-1 text-xs text-slate-500">Phase C preview shows the authorized draft structure. Rendered draft output requires the CR-001-E PublicRenderer integration.</p>
            </Card>

            <Card v-for="template in templates" :key="template.ulid">
                <h3 class="text-sm font-semibold text-slate-800">{{ template.name }} ({{ template.content_kind }})</h3>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    <li v-for="section in template.sections" :key="section.ulid">
                        Section {{ section.layout_variant }} — {{ section.components.length }} blocks
                        <span class="text-xs text-slate-400">({{ section.components.map((c) => c.type).join(', ') }})</span>
                    </li>
                </ul>
            </Card>

            <Card v-for="menu in navigationMenus" :key="menu.ulid">
                <h3 class="text-sm font-semibold text-slate-800">Menu {{ menu.name }}</h3>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    <li v-for="item in menu.items" :key="item.ulid">{{ item.label }} — {{ item.destination_type }}</li>
                </ul>
            </Card>
        </div>
    </AdminLayout>
</template>
