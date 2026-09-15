<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    title: '',
    body_html: '',
    excerpt: '',
    meta_title: '',
    meta_description: '',
    og_title: '',
    og_description: '',
});

const submit = () => {
    form.post('/admin/content/pages');
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <form class="mx-auto max-w-2xl space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-neutral-800">New Page</h1>

            <div>
                <label class="block text-sm text-neutral-600">Title</label>
                <input v-model="form.title" type="text" class="mt-1 w-full rounded border px-3 py-2" required autofocus />
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-600">Body</label>
                <textarea v-model="form.body_html" rows="12" class="mt-1 w-full rounded border px-3 py-2 font-mono text-sm" required></textarea>
                <p v-if="form.errors.body_html" class="mt-1 text-sm text-red-600">{{ form.errors.body_html }}</p>
            </div>

            <div>
                <label class="block text-sm text-neutral-600">Excerpt</label>
                <textarea v-model="form.excerpt" rows="3" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                <p v-if="form.errors.excerpt" class="mt-1 text-sm text-red-600">{{ form.errors.excerpt }}</p>
            </div>

            <fieldset class="space-y-4 rounded border p-4">
                <legend class="px-1 text-sm text-neutral-600">SEO</legend>

                <div>
                    <label class="block text-sm text-neutral-600">Meta title</label>
                    <input v-model="form.meta_title" type="text" class="mt-1 w-full rounded border px-3 py-2" />
                    <p v-if="form.errors.meta_title" class="mt-1 text-sm text-red-600">{{ form.errors.meta_title }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-600">Meta description</label>
                    <textarea v-model="form.meta_description" rows="2" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                    <p v-if="form.errors.meta_description" class="mt-1 text-sm text-red-600">{{ form.errors.meta_description }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-600">OG title</label>
                    <input v-model="form.og_title" type="text" class="mt-1 w-full rounded border px-3 py-2" />
                </div>

                <div>
                    <label class="block text-sm text-neutral-600">OG description</label>
                    <textarea v-model="form.og_description" rows="2" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                </div>
            </fieldset>

            <button type="submit" class="w-full rounded bg-neutral-800 px-3 py-2 text-white" :disabled="form.processing">
                Create page
            </button>
        </form>
    </div>
</template>
