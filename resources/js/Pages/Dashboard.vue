<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../Components/Admin/AdminLayout.vue';
import Card from '../Components/UI/Card.vue';
import Icon from '../Components/UI/Icon.vue';
import PageHeader from '../Components/UI/PageHeader.vue';

interface Counts {
    pages: number;
    articles: number;
    programs: number;
    campaigns: number;
    funds: number;
    themes: number;
}

defineProps<{ counts: Counts }>();

const modules = [
    { key: 'pages', label: 'Pages', href: '/admin/content/pages', icon: 'document', group: 'Content' },
    { key: 'articles', label: 'Articles', href: '/admin/content/articles', icon: 'document', group: 'Content' },
    { key: 'programs', label: 'Programs', href: '/admin/campaign/programs', icon: 'megaphone', group: 'Fundraising' },
    { key: 'campaigns', label: 'Campaigns', href: '/admin/campaign/campaigns', icon: 'megaphone', group: 'Fundraising' },
    { key: 'funds', label: 'Funds', href: '/admin/campaign/funds', icon: 'wallet', group: 'Fundraising' },
    { key: 'themes', label: 'Themes', href: '/admin/theme', icon: 'palette', group: 'Presentation' },
] as const;
</script>

<template>
    <AdminLayout>
        <template #header>
            <PageHeader title="Dashboard" description="An overview of the content and fundraising modules implemented so far." />
        </template>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link v-for="module in modules" :key="module.key" :href="module.href" class="block">
                <Card class="h-full transition hover:border-emerald-300 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                            <Icon :name="module.icon" class="h-5 w-5" />
                        </span>
                        <span class="text-2xl font-semibold text-slate-800">{{ counts[module.key] }}</span>
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-700">{{ module.label }}</p>
                    <p class="text-xs text-slate-400">{{ module.group }}</p>
                </Card>
            </Link>
        </div>

        <Card class="mt-6">
            <h2 class="text-sm font-semibold text-slate-800">Quick actions</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <Link href="/admin/campaign/campaigns/create" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    New campaign
                </Link>
                <Link href="/admin/campaign/programs/create" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    New program
                </Link>
                <Link href="/admin/campaign/funds/create" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    New fund
                </Link>
                <Link href="/admin/content/pages/create" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    New page
                </Link>
            </div>
        </Card>
    </AdminLayout>
</template>
