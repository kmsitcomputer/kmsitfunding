<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

interface ProgramProp {
    ulid: string;
    name: string;
    summary: string | null;
    description_html: string | null;
    status: string;
    edit_version: number;
}

const props = defineProps<{ program: ProgramProp }>();

const updateForm = useForm({
    name: props.program.name,
    summary: props.program.summary ?? '',
    description_html: props.program.description_html ?? '',
    expected_edit_version: props.program.edit_version,
});

const submitUpdate = () => {
    updateForm.patch(`/admin/campaign/programs/${props.program.ulid}`);
};

const publish = () => useForm({}).post(`/admin/campaign/programs/${props.program.ulid}/publish`);
const unpublish = () => useForm({}).post(`/admin/campaign/programs/${props.program.ulid}/unpublish`);
const archive = () => {
    if (confirm('Archive this program?')) {
        useForm({}).post(`/admin/campaign/programs/${props.program.ulid}/archive`);
    }
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-3xl space-y-8">
            <h1 class="text-xl font-semibold text-neutral-800">{{ program.name }}</h1>
            <p class="text-sm text-neutral-500">Status: {{ program.status }}</p>

            <div class="flex gap-2">
                <button v-if="program.status === 'DRAFT'" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" @click="publish">Publish</button>
                <button v-if="program.status === 'PUBLISHED'" class="rounded border px-3 py-2 text-sm" @click="unpublish">Unpublish</button>
                <button v-if="program.status !== 'ARCHIVED'" class="rounded border px-3 py-2 text-sm text-red-600" @click="archive">Archive</button>
            </div>

            <form class="space-y-4 rounded border bg-white p-4" @submit.prevent="submitUpdate">
                <h2 class="text-sm font-semibold text-neutral-600">Edit</h2>
                <div>
                    <label class="block text-sm text-neutral-600">Name</label>
                    <input v-model="updateForm.name" type="text" class="mt-1 w-full rounded border px-3 py-2" />
                    <p v-if="updateForm.errors.name" class="mt-1 text-sm text-red-600">{{ updateForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Summary</label>
                    <textarea v-model="updateForm.summary" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="block text-sm text-neutral-600">Description</label>
                    <textarea v-model="updateForm.description_html" rows="6" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                </div>
                <button type="submit" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" :disabled="updateForm.processing">Save</button>
            </form>
        </div>
    </div>
</template>
