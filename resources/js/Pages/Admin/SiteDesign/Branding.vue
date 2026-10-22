<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import FormField from '../../../Components/UI/FormField.vue';
import Input from '../../../Components/UI/Input.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import Select from '../../../Components/UI/Select.vue';

interface ThemeRow {
    ulid: string;
    name: string;
    slug: string;
    status: string;
}

interface BrandingRow {
    color_tokens: Record<string, string>;
    font_family: string;
    logo_theme_asset_id: number | null;
    favicon_theme_asset_id: number | null;
}

interface AssetRow {
    ulid: string;
    original_filename: string;
}

const props = defineProps<{
    theme: ThemeRow;
    branding: BrandingRow | null;
    assets: AssetRow[];
    logoAssetUlid?: string | null;
    faviconAssetUlid?: string | null;
}>();

const tokens = props.branding?.color_tokens ?? { primary: '#000000', secondary: '#000000', accent: '#000000', neutral_bg: '#ffffff', neutral_text: '#000000' };

const isDraft = computed(() => props.theme.status === 'DRAFT');

const logoAssetName = computed(() => props.assets.find((a) => a.ulid === (props.logoAssetUlid ?? ''))?.original_filename ?? null);
const faviconAssetName = computed(() => props.assets.find((a) => a.ulid === (props.faviconAssetUlid ?? ''))?.original_filename ?? null);

const brandForm = useForm({
    color_tokens: { ...tokens },
    font_family: props.branding?.font_family ?? 'system',
    logo_theme_asset_ulid: (props.logoAssetUlid ?? null) as string | null,
    favicon_theme_asset_ulid: (props.faviconAssetUlid ?? null) as string | null,
});

const logoForm = useForm({ file: null as File | null });

const submitBrand = () => {
    brandForm.post(`/admin/site-design/${props.theme.ulid}/branding`);
};

const submitLogo = () => {
    if (logoForm.file) {
        logoForm.post(`/admin/site-design/${props.theme.ulid}/logo`, { forceFormData: true });
    }
};

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    logoForm.file = input.files?.[0] ?? null;
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Site Design', href: '/admin/site-design' }, { label: 'Appearance' }]" />
        </template>
        <template #header>
            <PageHeader :title="`Appearance — ${theme.name}`" description="Brand colors, typography, and logo over the canonical Theme branding config." />
        </template>

        <div class="space-y-6">
            <p v-if="!isDraft" data-testid="site-design-readonly" class="max-w-2xl rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">This design is {{ theme.status }} — read-only. Clone it from Site Design to create an editable draft.</p>
            <Card v-if="!isDraft" class="max-w-2xl" data-testid="branding-readonly">
                <h2 class="text-sm font-semibold text-slate-800">Current appearance</h2>
                <dl class="mt-4 space-y-2 text-sm text-slate-600">
                    <div v-for="(_, token) in tokens" :key="token" class="flex items-center justify-between gap-4">
                        <dt>{{ token }}</dt>
                        <dd class="font-medium text-slate-800">{{ (tokens as Record<string, string>)[token] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt>Font family</dt>
                        <dd class="font-medium text-slate-800">{{ branding?.font_family ?? 'system' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt>Logo</dt>
                        <dd class="font-medium text-slate-800">{{ logoAssetName ?? 'None' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt>Favicon</dt>
                        <dd class="font-medium text-slate-800">{{ faviconAssetName ?? 'None' }}</dd>
                    </div>
                </dl>
            </Card>
            <Card v-if="isDraft" class="max-w-2xl" data-testid="branding-form">
                <h2 class="text-sm font-semibold text-slate-800">Brand colors &amp; typography</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitBrand">
                    <FormField v-for="(_, token) in brandForm.color_tokens" :key="token" :label="token" :for="`token-${token}`" :error="brandForm.errors[`color_tokens.${token}`]">
                        <Input :id="`token-${token}`" v-model="(brandForm.color_tokens as Record<string, string>)[token]" />
                    </FormField>
                    <FormField label="Font family" for="font-family" :error="brandForm.errors.font_family">
                        <Select id="font-family" v-model="brandForm.font_family">
                            <option value="system">System</option>
                            <option value="serif">Serif</option>
                            <option value="mono">Mono</option>
                        </Select>
                    </FormField>
                    <FormField label="Logo" for="logo-select" :error="brandForm.errors.logo_theme_asset_ulid" help="Current logo is preserved unless you pick another asset or explicitly clear it.">
                        <Select id="logo-select" v-model="brandForm.logo_theme_asset_ulid">
                            <option :value="null">No logo</option>
                            <option v-for="asset in assets" :key="asset.ulid" :value="asset.ulid">{{ asset.original_filename }} ({{ asset.ulid }})</option>
                        </Select>
                    </FormField>
                    <FormField label="Favicon" for="favicon-select" :error="brandForm.errors.favicon_theme_asset_ulid" help="Current favicon is preserved unless you pick another asset or explicitly clear it.">
                        <Select id="favicon-select" v-model="brandForm.favicon_theme_asset_ulid">
                            <option :value="null">No favicon</option>
                            <option v-for="asset in assets" :key="asset.ulid" :value="asset.ulid">{{ asset.original_filename }} ({{ asset.ulid }})</option>
                        </Select>
                    </FormField>
                    <Button type="submit" :disabled="brandForm.processing">Save appearance</Button>
                </form>
            </Card>

            <Card v-if="isDraft" class="max-w-2xl" data-testid="logo-upload-card">
                <h2 class="text-sm font-semibold text-slate-800">Logo upload</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitLogo">
                    <input type="file" accept="image/*" @change="onFileChange" />
                    <p v-if="logoForm.errors.file" class="text-sm text-red-600">{{ logoForm.errors.file }}</p>
                    <Button type="submit" :disabled="logoForm.processing || !logoForm.file">Upload logo</Button>
                </form>
                <ul v-if="assets.length > 0" class="mt-4 divide-y divide-slate-100">
                    <li v-for="asset in assets" :key="asset.ulid" class="py-2 text-sm text-slate-600">{{ asset.original_filename }} ({{ asset.ulid }})</li>
                </ul>
            </Card>
        </div>
    </AdminLayout>
</template>
