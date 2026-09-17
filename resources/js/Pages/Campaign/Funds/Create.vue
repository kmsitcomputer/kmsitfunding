<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import FormField from '../../../Components/UI/FormField.vue';
import Input from '../../../Components/UI/Input.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import Textarea from '../../../Components/UI/Textarea.vue';

const form = useForm({ name: '', code: '', restriction_note: '' });
const submit = () => form.post('/admin/campaign/funds');
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Funds', href: '/admin/campaign/funds' }, { label: 'Create' }]" />
        </template>
        <template #header>
            <PageHeader title="Create fund" description="A designation/restriction context — not a payment transaction or balance." />
        </template>

        <Card class="max-w-2xl">
            <form class="space-y-5" @submit.prevent="submit">
                <FormField label="Name" for="fund-name" :error="form.errors.name">
                    <Input id="fund-name" v-model="form.name" />
                </FormField>
                <FormField label="Code" for="fund-code" help="A short, unique reference code." :error="form.errors.code">
                    <Input id="fund-code" v-model="form.code" />
                </FormField>
                <FormField label="Restriction note" for="fund-note" help="Optional free-text description of the designation.">
                    <Textarea id="fund-note" v-model="form.restriction_note" :rows="3" />
                </FormField>
                <div class="flex items-center gap-2 pt-1">
                    <Button type="submit" :disabled="form.processing">Create fund</Button>
                    <Button as="a" href="/admin/campaign/funds" variant="secondary">Cancel</Button>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
