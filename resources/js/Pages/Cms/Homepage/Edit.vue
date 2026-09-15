<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

interface Page {
    ulid: string;
    title: string;
    status: string;
}

interface Assignment {
    page_id: number | null;
    page: Page | null;
}

const props = defineProps<{
    assignment: Assignment;
    eligiblePages: Page[];
}>();

const form = useForm({
    page_ulid: props.assignment.page?.ulid ?? '',
    expected_page_ulid: props.assignment.page?.ulid ?? '',
});

const submit = () => {
    form.patch('/admin/content/homepage');
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <form class="mx-auto max-w-md space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-neutral-800">Homepage</h1>

            <p class="text-sm text-neutral-600">
                Current designee:
                <span class="text-neutral-800">{{ props.assignment.page?.title ?? 'None' }}</span>
            </p>

            <div>
                <label class="block text-sm text-neutral-600">Designate page</label>
                <select v-model="form.page_ulid" class="mt-1 w-full rounded border px-3 py-2">
                    <option value="">None (clear)</option>
                    <option v-for="page in props.eligiblePages" :key="page.ulid" :value="page.ulid">
                        {{ page.title }} ({{ page.status }})
                    </option>
                </select>
                <p v-if="form.errors.page_ulid" class="mt-1 text-sm text-red-600">{{ form.errors.page_ulid }}</p>
            </div>

            <button type="submit" class="w-full rounded bg-neutral-800 px-3 py-2 text-white" :disabled="form.processing">
                Save
            </button>
        </form>
    </div>
</template>
