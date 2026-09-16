<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Alert from '../../../Components/UI/Alert.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import FormField from '../../../Components/UI/FormField.vue';
import Input from '../../../Components/UI/Input.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import Select from '../../../Components/UI/Select.vue';
import Textarea from '../../../Components/UI/Textarea.vue';

interface ProgramOption {
    ulid: string;
    name: string;
}

defineProps<{ programs: ProgramOption[] }>();

const form = useForm({
    name: '',
    program_ulid: '',
    summary: '',
    description_html: '',
    purpose: '',
    target_amount_minor: null as number | null,
    currency: 'IDR',
    starts_at: '',
    ends_at: '',
});

const submit = () => {
    form.transform((data) => ({ ...data, program_ulid: data.program_ulid || null })).post('/admin/campaign/campaigns');
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Campaigns', href: '/admin/campaign/campaigns' }, { label: 'New' }]" />
        </template>
        <template #header>
            <PageHeader title="New campaign" description="Fund assignment happens after creation, once the campaign is in Draft." />
        </template>

        <Card class="max-w-3xl">
            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormField label="Name" for="c-name" :error="form.errors.name" class="sm:col-span-2">
                        <Input id="c-name" v-model="form.name" />
                    </FormField>
                    <FormField label="Program" for="c-program" help="Optional — attach to an organizing initiative.">
                        <Select id="c-program" v-model="form.program_ulid">
                            <option value="">— none —</option>
                            <option v-for="program in programs" :key="program.ulid" :value="program.ulid">{{ program.name }}</option>
                        </Select>
                    </FormField>
                    <FormField label="Purpose" for="c-purpose">
                        <Input id="c-purpose" v-model="form.purpose" placeholder="e.g. Emergency food aid" />
                    </FormField>
                </div>

                <FormField label="Summary" for="c-summary">
                    <Textarea id="c-summary" v-model="form.summary" :rows="2" />
                </FormField>
                <FormField label="Description" for="c-description">
                    <Textarea id="c-description" v-model="form.description_html" :rows="6" />
                </FormField>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <FormField label="Target amount (minor units)" for="c-amount" :error="form.errors.target_amount_minor" help="e.g. 50000000 = Rp 500.000">
                        <Input id="c-amount" v-model.number="form.target_amount_minor" type="number" min="0" />
                    </FormField>
                    <FormField label="Currency" for="c-currency">
                        <Input id="c-currency" v-model="form.currency" placeholder="IDR" />
                    </FormField>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormField label="Starts at" for="c-starts">
                        <Input id="c-starts" v-model="form.starts_at" type="date" />
                    </FormField>
                    <FormField label="Ends at" for="c-ends" :error="form.errors.ends_at">
                        <Input id="c-ends" v-model="form.ends_at" type="date" />
                    </FormField>
                </div>

                <Alert tone="info">A Fund must be assigned before this campaign can be published — you'll do that from the campaign workspace after creation.</Alert>

                <div class="flex items-center gap-2 pt-1">
                    <Button type="submit" :disabled="form.processing">Create campaign</Button>
                    <Button as="a" href="/admin/campaign/campaigns" variant="secondary">Cancel</Button>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
