<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from './Button.vue';
import Icon from './Icon.vue';

interface MediaAsset {
    ulid: string;
    url: string;
    original_filename: string;
}

const props = defineProps<{
    assets: MediaAsset[];
    uploadUrl: string;
    archiveUrlFor: (ulid: string) => string;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const uploadForm = useForm<{ file: File | null }>({ file: null });

function onFileChosen(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    uploadForm.file = file;

    if (file) {
        uploadForm.post(props.uploadUrl, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                uploadForm.reset();
                if (fileInput.value) fileInput.value.value = '';
            },
        });
    }
}

function archive(ulid: string) {
    if (confirm('Archive this media item?')) {
        useForm({}).post(props.archiveUrlFor(ulid), { preserveScroll: true });
    }
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="assets.length > 0" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div v-for="asset in assets" :key="asset.ulid" class="group relative overflow-hidden rounded-lg border border-slate-200">
                <img :src="asset.url" :alt="asset.original_filename" class="aspect-square w-full object-cover" />
                <button
                    type="button"
                    class="absolute right-1.5 top-1.5 flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-slate-600 opacity-0 shadow-sm transition group-hover:opacity-100 hover:text-red-600"
                    aria-label="Archive media"
                    @click="archive(asset.ulid)"
                >
                    <Icon name="trash" class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
        <p v-else class="text-sm text-slate-500">No media uploaded yet.</p>

        <div>
            <input :ref="(el) => (fileInput = el as HTMLInputElement)" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onFileChosen" />
            <Button variant="secondary" type="button" :disabled="uploadForm.processing" @click="fileInput?.click()">
                <Icon name="upload" class="h-4 w-4" />
                {{ uploadForm.processing ? 'Uploading…' : 'Upload image' }}
            </Button>
            <p v-if="uploadForm.errors.file" class="mt-1.5 text-sm text-red-600">{{ uploadForm.errors.file }}</p>
        </div>
    </div>
</template>
