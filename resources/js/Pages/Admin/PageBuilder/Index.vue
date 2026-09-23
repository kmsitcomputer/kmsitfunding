<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Button from '../../../Components/UI/Button.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import Icon from '../../../Components/UI/Icon.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface CanvasRow {
    label: string;
    contentKind: string;
    templateUlid: string;
    blockCount: number;
}

interface ThemeRow {
    ulid: string;
    name: string;
    status: string;
}

defineProps<{ theme: ThemeRow; canvases: CanvasRow[]; isDraft: boolean; canUpdate: boolean; previewUrl: string }>();
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Site Design', href: '/admin/site-design' }, { label: 'Page Builder' }]" />
        </template>
        <template #header>
            <PageHeader title="Page Builder" description="Compose the shared layout for Home, Pages, and Articles. Each canvas is one layout shared by every page of its type.">
                <template #badge>
                    <StatusBadge :status="theme.status" />
                </template>
                <template #actions>
                    <Button v-if="isDraft" as="a" variant="secondary" :href="previewUrl">Preview</Button>
                </template>
            </PageHeader>
        </template>

        <div class="space-y-6">
            <p v-if="!isDraft" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                This design is {{ theme.status }} and read-only. Clone it to a draft from Site Design to edit blocks.
            </p>
            <Card :padded="false">
                <EmptyState v-if="canvases.length === 0" icon="palette" title="No page layouts yet" description="Layouts are created with the theme." />
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="canvas in canvases" :key="canvas.templateUlid" class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                <Icon name="palette" class="h-4.5 w-4.5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ canvas.label }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ canvas.blockCount }} blocks</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <Link :href="`/admin/page-builder/templates/${canvas.templateUlid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Open</Link>
                        </div>
                    </li>
                </ul>
            </Card>
        </div>
    </AdminLayout>
</template>
