<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import FormField from '../../../Components/UI/FormField.vue';
import Input from '../../../Components/UI/Input.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import Select from '../../../Components/UI/Select.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface ThemeRow {
    ulid: string;
    name: string;
    slug: string;
    status: string;
}

interface NavItem {
    ulid: string;
    label: string;
    destination_type: string;
    destination_route: string | null;
    destination_content_kind: string | null;
    destination_content_ulid: string | null;
    destination_external_url: string | null;
    visible: boolean;
    visible_desktop: boolean;
    visible_mobile: boolean;
    parent_id: number | null;
}

interface NavMenu {
    ulid: string;
    code: string;
    name: string;
    items: NavItem[];
}

const props = defineProps<{ theme: ThemeRow; menus: NavMenu[]; activeMenu?: NavMenu | null }>();

const isDraft = computed(() => props.theme.status === 'DRAFT');

const menuForm = useForm({ code: '', name: '' });

const submitMenu = () => {
    menuForm.post(`/admin/site-design/${props.theme.ulid}/menus`, { onSuccess: () => menuForm.reset() });
};

const shownMenu = props.activeMenu ?? props.menus[0] ?? null;

const itemForm = useForm({
    label: '',
    destination_type: 'EXTERNAL_URL',
    destination_route: null as string | null,
    destination_content_kind: 'page' as string | null,
    destination_content_ulid: null as string | null,
    destination_external_url: '' as string | null,
    parent_id: null as number | null,
    visible_desktop: true,
    visible_mobile: true,
});

const submitItem = () => {
    if (shownMenu) {
        itemForm.post(`/admin/site-design/menus/${shownMenu.ulid}/items`, { onSuccess: () => itemForm.reset() });
    }
};

const editingUlid = ref<string | null>(null);
const editForm = useForm({
    label: '',
    destination_type: 'EXTERNAL_URL',
    destination_external_url: null as string | null,
    visible_desktop: true,
    visible_mobile: true,
});

const startEdit = (item: NavItem) => {
    editingUlid.value = item.ulid;
    editForm.label = item.label;
    editForm.destination_type = item.destination_type;
    editForm.destination_external_url = item.destination_external_url;
    editForm.visible_desktop = item.visible_desktop;
    editForm.visible_mobile = item.visible_mobile;
};

const submitEdit = (ulid: string) => {
    editForm.patch(`/admin/site-design/navigation-items/${ulid}`, { onSuccess: () => { editingUlid.value = null; } });
};

const deleteItem = (ulid: string) => {
    router.delete(`/admin/site-design/navigation-items/${ulid}`);
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Site Design', href: '/admin/site-design' }, { label: 'Menus' }]" />
        </template>
        <template #header>
            <PageHeader :title="`Menus — ${theme.name}`" description="Navigation menus over the canonical Theme navigation structure." />
        </template>

        <div class="space-y-6">
            <p v-if="!isDraft" data-testid="site-design-readonly" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">This design is {{ theme.status }} — read-only. Clone it from Site Design to create an editable draft.</p>
            <Card v-if="isDraft" class="max-w-xl" data-testid="new-menu-card">
                <h2 class="text-sm font-semibold text-slate-800">New menu</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitMenu">
                    <FormField label="Code" for="menu-code" :error="menuForm.errors.code">
                        <Input id="menu-code" v-model="menuForm.code" />
                    </FormField>
                    <FormField label="Name" for="menu-name" :error="menuForm.errors.name">
                        <Input id="menu-name" v-model="menuForm.name" />
                    </FormField>
                    <Button type="submit" :disabled="menuForm.processing">Create menu</Button>
                </form>
            </Card>

            <Card :padded="false">
                <EmptyState v-if="menus.length === 0" icon="menu" title="No menus yet" />
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="menu in menus" :key="menu.ulid" class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800">{{ menu.name }} ({{ menu.code }})</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ menu.items.length }} items</p>
                        </div>
                        <Link :href="`/admin/site-design/menus/${menu.ulid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Manage</Link>
                    </li>
                </ul>
            </Card>

            <Card v-if="shownMenu" :padded="false">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-800">{{ shownMenu.name }}</h2>
                </div>
                <EmptyState v-if="shownMenu.items.length === 0" icon="menu" title="No items in this menu" />
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="item in shownMenu.items" :key="item.ulid" class="flex items-center justify-between gap-4 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <template v-if="isDraft && editingUlid === item.ulid">
                                <form class="space-y-3" @submit.prevent="submitEdit(item.ulid)">
                                    <FormField label="Label" :for="`edit-label-${item.ulid}`">
                                        <Input :id="`edit-label-${item.ulid}`" v-model="editForm.label" />
                                    </FormField>
                                    <FormField label="Destination type" :for="`edit-dest-${item.ulid}`">
                                        <Select :id="`edit-dest-${item.ulid}`" v-model="editForm.destination_type">
                                            <option value="SYSTEM_ROUTE">Internal link</option>
                                            <option value="CMS_CONTENT">Page</option>
                                            <option value="EXTERNAL_URL">URL</option>
                                        </Select>
                                    </FormField>
                                    <FormField v-if="editForm.destination_type === 'EXTERNAL_URL'" label="URL" :for="`edit-url-${item.ulid}`">
                                        <Input :id="`edit-url-${item.ulid}`" v-model="editForm.destination_external_url" />
                                    </FormField>
                                    <div class="flex items-center gap-4 text-sm text-slate-700">
                                        <label class="flex items-center gap-1.5"><input v-model="editForm.visible_desktop" type="checkbox" /> Desktop</label>
                                        <label class="flex items-center gap-1.5"><input v-model="editForm.visible_mobile" type="checkbox" /> Mobile</label>
                                    </div>
                                    <div class="flex gap-2">
                                        <Button type="submit" :disabled="editForm.processing">Save</Button>
                                        <Button variant="secondary" @click="editingUlid = null">Cancel</Button>
                                    </div>
                                </form>
                            </template>
                            <template v-else>
                                <p class="truncate text-sm font-medium text-slate-800">{{ item.label }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ item.destination_type }}</p>
                            </template>
                        </div>
                        <div v-if="isDraft && editingUlid !== item.ulid" class="flex shrink-0 items-center gap-2" data-testid="item-actions">
                            <StatusBadge :status="item.visible ? 'visible' : 'hidden'" />
                            <span class="text-xs text-slate-500">Desktop: {{ item.visible_desktop ? 'on' : 'off' }} · Mobile: {{ item.visible_mobile ? 'on' : 'off' }}</span>
                            <Button variant="ghost" @click="startEdit(item)">Edit</Button>
                            <Button variant="ghost" @click="deleteItem(item.ulid)">Delete</Button>
                        </div>
                    </li>
                </ul>
                <div v-if="isDraft" class="border-t border-slate-200 px-5 py-4" data-testid="new-item-form">
                    <h3 class="text-sm font-semibold text-slate-800">New item</h3>
                    <form class="mt-3 space-y-4" @submit.prevent="submitItem">
                        <FormField label="Label" for="item-label" :error="itemForm.errors.label">
                            <Input id="item-label" v-model="itemForm.label" />
                        </FormField>
                        <FormField label="Destination type" for="item-dest" :error="itemForm.errors.destination_type">
                            <Select id="item-dest" v-model="itemForm.destination_type">
                                <option value="SYSTEM_ROUTE">Internal link</option>
                                <option value="CMS_CONTENT">Page</option>
                                <option value="EXTERNAL_URL">URL</option>
                            </Select>
                        </FormField>
                        <FormField v-if="itemForm.destination_type === 'SYSTEM_ROUTE'" label="Route name" for="item-route" :error="itemForm.errors.destination_route">
                            <Input id="item-route" v-model="itemForm.destination_route" placeholder="home" />
                        </FormField>
                        <FormField v-if="itemForm.destination_type === 'EXTERNAL_URL'" label="URL" for="item-url" :error="itemForm.errors.destination_external_url">
                            <Input id="item-url" v-model="itemForm.destination_external_url" placeholder="https://" />
                        </FormField>
                        <div class="flex items-center gap-4 text-sm text-slate-700">
                            <label class="flex items-center gap-1.5"><input v-model="itemForm.visible_desktop" type="checkbox" /> Desktop</label>
                            <label class="flex items-center gap-1.5"><input v-model="itemForm.visible_mobile" type="checkbox" /> Mobile</label>
                        </div>
                        <Button type="submit" :disabled="itemForm.processing">Add item</Button>
                    </form>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
