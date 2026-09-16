<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface FundRow {
    ulid: string;
    name: string;
    code: string;
    status: string;
    restriction_note: string | null;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ funds: Paginated<FundRow> }>();

const archive = (ulid: string) => {
    if (confirm('Archive this fund?')) {
        useForm({}).post(`/admin/campaign/funds/${ulid}/archive`);
    }
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Funds' }]" />
        </template>
        <template #header>
            <PageHeader title="Funds" description="Designation/restriction contexts a Campaign can be attached to.">
                <template #actions>
                    <Button as="a" href="/admin/campaign/funds/create">Create fund</Button>
                </template>
            </PageHeader>
        </template>

        <Card :padded="false">
            <EmptyState v-if="funds.data.length === 0" icon="wallet" title="No funds yet" description="Create a fund before publishing a campaign against it.">
                <template #action>
                    <Button as="a" href="/admin/campaign/funds/create">Create fund</Button>
                </template>
            </EmptyState>

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="fund in funds.data" :key="fund.ulid" class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-medium text-slate-800">{{ fund.name }}</p>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-500">{{ fund.code }}</span>
                        </div>
                        <p v-if="fund.restriction_note" class="mt-0.5 truncate text-sm text-slate-500">{{ fund.restriction_note }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <StatusBadge :status="fund.status" />
                        <Link :href="`/admin/campaign/funds/${fund.ulid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Edit</Link>
                        <button v-if="fund.status === 'ACTIVE'" type="button" class="text-sm font-medium text-slate-400 hover:text-red-600" @click="archive(fund.ulid)">
                            Archive
                        </button>
                    </div>
                </li>
            </ul>
        </Card>
    </AdminLayout>
</template>
