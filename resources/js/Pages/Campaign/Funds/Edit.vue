<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import FormField from '../../../Components/UI/FormField.vue';
import Input from '../../../Components/UI/Input.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';
import Textarea from '../../../Components/UI/Textarea.vue';

interface FundProp {
    ulid: string;
    name: string;
    code: string;
    status: string;
    restriction_note: string | null;
}

const props = defineProps<{ fund: FundProp }>();

const form = useForm({
    name: props.fund.name,
    restriction_note: props.fund.restriction_note ?? '',
});

const submit = () => form.patch(`/admin/campaign/funds/${props.fund.ulid}`);

const archive = () => {
    if (confirm('Archive this fund?')) {
        useForm({}).post(`/admin/campaign/funds/${props.fund.ulid}/archive`);
    }
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Funds', href: '/admin/campaign/funds' }, { label: fund.name }]" />
        </template>
        <template #header>
            <PageHeader :title="fund.name" :description="`Code: ${fund.code}`">
                <template #badge>
                    <StatusBadge :status="fund.status" />
                </template>
                <template #actions>
                    <Button v-if="fund.status === 'ACTIVE'" variant="danger" @click="archive">Archive fund</Button>
                </template>
            </PageHeader>
        </template>

        <Card class="max-w-2xl">
            <form class="space-y-5" @submit.prevent="submit">
                <FormField label="Name" for="fund-name" :error="form.errors.name">
                    <Input id="fund-name" v-model="form.name" />
                </FormField>
                <FormField label="Restriction note" for="fund-note" help="Optional free-text description of the designation.">
                    <Textarea id="fund-note" v-model="form.restriction_note" :rows="3" />
                </FormField>
                <div class="flex items-center gap-2 pt-1">
                    <Button type="submit" :disabled="form.processing">Save changes</Button>
                    <Button as="a" href="/admin/campaign/funds" variant="secondary">Back to funds</Button>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
