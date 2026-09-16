<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

interface FundRow {
    ulid: string;
    name: string;
    code: string;
    status: string;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ funds: Paginated<FundRow> }>();

const createForm = useForm({ name: '', code: '', restriction_note: '' });

const submit = () => {
    createForm.post('/admin/campaign/funds', { onSuccess: () => createForm.reset() });
};

const archive = (ulid: string) => {
    if (confirm('Archive this fund?')) {
        useForm({}).post(`/admin/campaign/funds/${ulid}/archive`);
    }
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-3xl space-y-8">
            <h1 class="text-xl font-semibold text-neutral-800">Funds</h1>

            <form class="space-y-4 rounded border bg-white p-4" @submit.prevent="submit">
                <h2 class="text-sm font-semibold text-neutral-600">New Fund</h2>
                <div>
                    <label class="block text-sm text-neutral-600">Name</label>
                    <input v-model="createForm.name" type="text" class="mt-1 w-full rounded border px-3 py-2" required />
                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Code</label>
                    <input v-model="createForm.code" type="text" class="mt-1 w-full rounded border px-3 py-2" required />
                    <p v-if="createForm.errors.code" class="mt-1 text-sm text-red-600">{{ createForm.errors.code }}</p>
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Restriction note</label>
                    <textarea v-model="createForm.restriction_note" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                </div>
                <button type="submit" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" :disabled="createForm.processing">
                    Create
                </button>
            </form>

            <table class="w-full rounded border bg-white text-sm">
                <thead>
                    <tr class="border-b text-left text-neutral-500">
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="fund in funds.data" :key="fund.ulid" class="border-b last:border-0">
                        <td class="px-3 py-2 text-neutral-800">{{ fund.name }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ fund.code }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ fund.status }}</td>
                        <td class="px-3 py-2 text-right">
                            <button v-if="fund.status === 'ACTIVE'" class="text-sm text-red-600 underline" @click="archive(fund.ulid)">Archive</button>
                        </td>
                    </tr>
                    <tr v-if="funds.data.length === 0">
                        <td class="px-3 py-6 text-center text-neutral-500" colspan="4">No funds yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
