<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

interface PageRow {
    ulid: string;
    title: string;
    status: string;
    updated_at: string;
}

interface Paginated<T> {
    data: T[];
}

const props = defineProps<{ pages: Paginated<PageRow> }>();

const archiveForm = useForm({});
const unpublishForm = useForm({});

const archive = (page: PageRow) => {
    if (! confirm(`Archive "${page.title}"? This cannot be undone.`)) {
        return;
    }

    archiveForm.post(`/admin/content/pages/${page.ulid}/archive`);
};

const unpublish = (page: PageRow) => {
    unpublishForm.post(`/admin/content/pages/${page.ulid}/unpublish`);
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-4xl space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-neutral-800">Pages</h1>
                <Link href="/admin/content/pages/create" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white">
                    New Page
                </Link>
            </div>

            <table class="w-full rounded border bg-white text-sm">
                <thead>
                    <tr class="border-b text-left text-neutral-500">
                        <th class="px-3 py-2">Title</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Updated</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="page in props.pages.data" :key="page.ulid" class="border-b last:border-0">
                        <td class="px-3 py-2 text-neutral-800">{{ page.title }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ page.status }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ page.updated_at }}</td>
                        <td class="px-3 py-2 text-right space-x-2">
                            <Link :href="`/admin/content/pages/${page.ulid}`" class="text-neutral-800 underline">Edit</Link>
                            <button
                                v-if="page.status === 'PUBLISHED'"
                                type="button"
                                class="text-neutral-600 underline"
                                @click="unpublish(page)"
                            >
                                Unpublish
                            </button>
                            <button
                                v-if="page.status === 'DRAFT' || page.status === 'RETIRED'"
                                type="button"
                                class="text-red-600 underline"
                                @click="archive(page)"
                            >
                                Archive
                            </button>
                        </td>
                    </tr>
                    <tr v-if="props.pages.data.length === 0">
                        <td class="px-3 py-6 text-center text-neutral-500" colspan="4">No pages yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
