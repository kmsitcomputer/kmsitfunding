<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface Asset {
    ulid: string;
    original_filename: string;
    mime_type: string;
    extension: string;
    status: string;
    alt_text: string | null;
    caption: string | null;
    url: string | null;
}

interface Paginated<T> {
    data: T[];
}

const props = defineProps<{ assets: Paginated<Asset> }>();

const uploadForm = useForm({
    file: null as File | null,
    alt_text: '',
    caption: '',
});

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    uploadForm.file = target.files?.[0] ?? null;
};

const upload = () => {
    uploadForm.post('/admin/content/media', {
        forceFormData: true,
        onSuccess: () => uploadForm.reset(),
    });
};

const metadataForms = reactive<Record<string, ReturnType<typeof useForm>>>({});

const metadataForm = (asset: Asset) => {
    if (! metadataForms[asset.ulid]) {
        metadataForms[asset.ulid] = useForm({
            alt_text: asset.alt_text ?? '',
            caption: asset.caption ?? '',
        });
    }

    return metadataForms[asset.ulid];
};

const saveMetadata = (asset: Asset) => {
    metadataForm(asset).patch(`/admin/content/media/${asset.ulid}`);
};

const archiveForm = useForm({});

const archive = (asset: Asset) => {
    if (! confirm(`Archive "${asset.original_filename}"?`)) {
        return;
    }

    archiveForm.post(`/admin/content/media/${asset.ulid}/archive`);
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-4xl space-y-8">
            <h1 class="text-xl font-semibold text-neutral-800">Media Library</h1>

            <form class="space-y-4 rounded border bg-white p-4" @submit.prevent="upload">
                <h2 class="text-sm font-semibold text-neutral-600">Upload</h2>

                <div>
                    <input type="file" class="mt-1 w-full rounded border px-3 py-2" @change="onFileChange" />
                    <p v-if="uploadForm.errors.file" class="mt-1 text-sm text-red-600">{{ uploadForm.errors.file }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-600">Alt text</label>
                    <input v-model="uploadForm.alt_text" type="text" class="mt-1 w-full rounded border px-3 py-2" />
                </div>

                <div>
                    <label class="block text-sm text-neutral-600">Caption</label>
                    <input v-model="uploadForm.caption" type="text" class="mt-1 w-full rounded border px-3 py-2" />
                </div>

                <button type="submit" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white" :disabled="uploadForm.processing">
                    Upload
                </button>
            </form>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div v-for="asset in props.assets.data" :key="asset.ulid" class="space-y-2 rounded border bg-white p-4">
                    <img v-if="asset.url && asset.mime_type.startsWith('image/')" :src="asset.url" :alt="asset.alt_text ?? ''" class="max-h-40 w-full rounded object-cover" />
                    <p class="text-sm text-neutral-800">{{ asset.original_filename }}</p>
                    <p class="text-xs text-neutral-500">{{ asset.mime_type }} — {{ asset.status }}</p>

                    <template v-if="asset.status === 'ACTIVE'">
                        <input v-model="metadataForm(asset).alt_text" type="text" placeholder="Alt text" class="w-full rounded border px-2 py-1 text-sm" />
                        <input v-model="metadataForm(asset).caption" type="text" placeholder="Caption" class="w-full rounded border px-2 py-1 text-sm" />
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded border px-2 py-1 text-xs text-neutral-800"
                                :disabled="metadataForm(asset).processing"
                                @click="saveMetadata(asset)"
                            >
                                Save
                            </button>
                            <button type="button" class="rounded border border-red-600 px-2 py-1 text-xs text-red-600" @click="archive(asset)">
                                Archive
                            </button>
                        </div>
                    </template>
                </div>

                <p v-if="props.assets.data.length === 0" class="text-sm text-neutral-500">No media uploaded yet.</p>
            </div>
        </div>
    </div>
</template>
