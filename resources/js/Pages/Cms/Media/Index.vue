<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import Icon from '../../../Components/UI/Icon.vue';
import Input from '../../../Components/UI/Input.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';

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

const uploadForm = useForm({ file: null as File | null, alt_text: '', caption: '' });

const onFileChange = (event: Event) => {
    uploadForm.file = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const upload = () => uploadForm.post('/admin/content/media', { forceFormData: true, onSuccess: () => uploadForm.reset() });

const metadataForms = reactive<Record<string, ReturnType<typeof useForm>>>({});

const metadataForm = (asset: Asset) => {
    if (!metadataForms[asset.ulid]) {
        metadataForms[asset.ulid] = useForm({ alt_text: asset.alt_text ?? '', caption: asset.caption ?? '' });
    }
    return metadataForms[asset.ulid];
};

const saveMetadata = (asset: Asset) => metadataForm(asset).patch(`/admin/content/media/${asset.ulid}`);

const archiveForm = useForm({});
const archive = (asset: Asset) => {
    if (confirm(`Archive "${asset.original_filename}"?`)) {
        archiveForm.post(`/admin/content/media/${asset.ulid}/archive`);
    }
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Media' }]" />
        </template>
        <template #header>
            <PageHeader title="Media library" description="Images and documents used across CMS content." />
        </template>

        <div class="space-y-6">
            <Card>
                <h2 class="text-sm font-semibold text-slate-800">Upload</h2>
                <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="upload">
                    <input type="file" class="text-sm" @change="onFileChange" />
                    <Input v-model="uploadForm.alt_text" placeholder="Alt text" class="!w-48" />
                    <Input v-model="uploadForm.caption" placeholder="Caption" class="!w-48" />
                    <Button type="submit" :disabled="uploadForm.processing">
                        <Icon name="upload" class="h-4 w-4" />
                        Upload
                    </Button>
                </form>
                <p v-if="uploadForm.errors.file" class="mt-2 text-sm text-red-600">{{ uploadForm.errors.file }}</p>
            </Card>

            <EmptyState v-if="props.assets.data.length === 0" icon="image" title="No media uploaded yet" />
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="asset in props.assets.data" :key="asset.ulid">
                    <img v-if="asset.url && asset.mime_type.startsWith('image/')" :src="asset.url" :alt="asset.alt_text ?? ''" class="mb-3 aspect-video w-full rounded-lg object-cover" />
                    <p class="truncate text-sm font-medium text-slate-800">{{ asset.original_filename }}</p>
                    <p class="text-xs text-slate-500">{{ asset.mime_type }} — {{ asset.status }}</p>

                    <template v-if="asset.status === 'ACTIVE'">
                        <div class="mt-3 space-y-2">
                            <Input v-model="metadataForm(asset).alt_text" placeholder="Alt text" />
                            <Input v-model="metadataForm(asset).caption" placeholder="Caption" />
                        </div>
                        <div class="mt-3 flex gap-2">
                            <Button variant="secondary" :disabled="metadataForm(asset).processing" @click="saveMetadata(asset)">Save</Button>
                            <Button variant="danger" @click="archive(asset)">Archive</Button>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
