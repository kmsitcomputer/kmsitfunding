<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

interface ProgramRow {
    ulid: string;
    name: string;
    slug: string;
    status: string;
    updated_at: string;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ programs: Paginated<ProgramRow> }>();

const createForm = useForm({ name: '', slug: '', summary: '' });

const submit = () => {
    createForm.post('/admin/campaign/programs', { onSuccess: () => createForm.reset() });
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-3xl space-y-8">
            <h1 class="text-xl font-semibold text-neutral-800">Programs</h1>

            <form class="space-y-4 rounded border bg-white p-4" @submit.prevent="submit">
                <h2 class="text-sm font-semibold text-neutral-600">New Program</h2>
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
                    <tr v-for="program in programs.data" :key="program.ulid" class="border-b last:border-0">
                        <td class="px-3 py-2 text-neutral-800">{{ program.name }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ program.status }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ program.updated_at }}</td>
                        <td class="px-3 py-2 text-right">
                            <Link :href="`/admin/campaign/programs/${program.ulid}`" class="text-neutral-800 underline">Manage</Link>
                        </td>
                    </tr>
                    <tr v-if="programs.data.length === 0">
                        <td class="px-3 py-6 text-center text-neutral-500" colspan="4">No programs yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
