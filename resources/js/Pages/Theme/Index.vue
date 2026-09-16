<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

interface ThemeRow {
    ulid: string;
    name: string;
    slug: string;
    status: string;
    is_system_default: boolean;
    updated_at: string;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ themes: Paginated<ThemeRow> }>();

const createForm = useForm({ name: '', slug: '' });

const submit = () => {
    createForm.post('/admin/theme', { onSuccess: () => createForm.reset() });
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-3xl space-y-8">
            <h1 class="text-xl font-semibold text-neutral-800">Themes</h1>

            <form class="space-y-4 rounded border bg-white p-4" @submit.prevent="submit">
                <h2 class="text-sm font-semibold text-neutral-600">New Theme</h2>
                <div>
                    <label class="block text-sm text-neutral-600">Name</label>
                    <input v-model="createForm.name" type="text" class="mt-1 w-full rounded border px-3 py-2" required />
                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                </div>
                <button type="submit" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" :disabled="createForm.processing">
                    Create
                </button>
            </form>

            <table class="w-full rounded border bg-white text-sm">
                <thead>
                    <tr class="border-b text-left text-neutral-500">
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Updated</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="theme in themes.data" :key="theme.ulid" class="border-b last:border-0">
                        <td class="px-3 py-2 text-neutral-800">
                            {{ theme.name }}
                            <span v-if="theme.is_system_default" class="ml-1 text-xs text-neutral-400">(system default)</span>
                        </td>
                        <td class="px-3 py-2 text-neutral-600">{{ theme.status }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ theme.updated_at }}</td>
                        <td class="px-3 py-2 text-right">
                            <Link :href="`/admin/theme/${theme.ulid}`" class="text-neutral-800 underline">Manage</Link>
                        </td>
                    </tr>
                    <tr v-if="themes.data.length === 0">
                        <td class="px-3 py-6 text-center text-neutral-500" colspan="4">No themes yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
