<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', slug: '', summary: '', description_html: '' });

const submit = () => form.post('/admin/campaign/programs');
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-2xl">
            <h1 class="mb-6 text-xl font-semibold text-neutral-800">New Program</h1>
            <form class="space-y-4 rounded border bg-white p-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm text-neutral-600">Name</label>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded border px-3 py-2" required />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Summary</label>
                    <textarea v-model="form.summary" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Description</label>
                    <textarea v-model="form.description_html" rows="6" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                </div>
                <button type="submit" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" :disabled="form.processing">Create</button>
            </form>
        </div>
    </div>
</template>
