<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

interface FundOption {
    ulid: string;
    name: string;
}

defineProps<{ funds: FundOption[] }>();

const form = useForm({
    name: '',
    slug: '',
    summary: '',
    description_html: '',
    purpose: '',
    target_amount_minor: null as number | null,
    currency: '',
    starts_at: '',
    ends_at: '',
});

const submit = () => form.post('/admin/campaign/campaigns');
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-2xl">
            <h1 class="mb-6 text-xl font-semibold text-neutral-800">New Campaign</h1>
            <form class="space-y-4 rounded border bg-white p-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm text-neutral-600">Name</label>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded border px-3 py-2" required />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Purpose</label>
                    <input v-model="form.purpose" type="text" class="mt-1 w-full rounded border px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Description</label>
                    <textarea v-model="form.description_html" rows="6" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-neutral-600">Target amount (minor units)</label>
                        <input v-model.number="form.target_amount_minor" type="number" min="0" class="mt-1 w-full rounded border px-3 py-2" />
                        <p v-if="form.errors.target_amount_minor" class="mt-1 text-sm text-red-600">{{ form.errors.target_amount_minor }}</p>
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600">Currency</label>
                        <input v-model="form.currency" type="text" maxlength="3" placeholder="IDR" class="mt-1 w-full rounded border px-3 py-2" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-neutral-600">Starts at</label>
                        <input v-model="form.starts_at" type="date" class="mt-1 w-full rounded border px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600">Ends at</label>
                        <input v-model="form.ends_at" type="date" class="mt-1 w-full rounded border px-3 py-2" />
                        <p v-if="form.errors.ends_at" class="mt-1 text-sm text-red-600">{{ form.errors.ends_at }}</p>
                    </div>
                </div>
                <button type="submit" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" :disabled="form.processing">Create</button>
            </form>
        </div>
    </div>
</template>
