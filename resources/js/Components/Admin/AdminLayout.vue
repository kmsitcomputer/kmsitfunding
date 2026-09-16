<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from '../UI/Icon.vue';

/**
 * The first reusable admin application shell in this codebase (no
 * equivalent existed after IMP-005/006 — confirmed by repository audit).
 * Deliberately generic/reusable, not coupled to Campaign business logic,
 * so later IMPs (and, separately, a future authorized pass over the
 * existing CMS/Theme admin pages) can adopt it without rework. Navigation
 * is presentation only — every link still lands on a backend route
 * enforcing its own Policy; this sidebar grants nothing by itself.
 */
const page = usePage();
const userEmail = computed(() => (page.props.auth as { user: { email: string } | null } | undefined)?.user?.email ?? null);
const currentPath = computed(() => page.url.split('?')[0]);

const sidebarOpen = ref(false);

const nav = [
    { label: 'Dashboard', href: '/dashboard', icon: 'dashboard', match: '/dashboard' },
    { label: 'Content', href: '/admin/content/pages', icon: 'document', match: '/admin/content' },
    { label: 'Programs', href: '/admin/campaign/programs', icon: 'megaphone', match: '/admin/campaign/programs' },
    { label: 'Campaigns', href: '/admin/campaign/campaigns', icon: 'megaphone', match: '/admin/campaign/campaigns' },
    { label: 'Funds', href: '/admin/campaign/funds', icon: 'wallet', match: '/admin/campaign/funds' },
    { label: 'Theme', href: '/admin/theme', icon: 'palette', match: '/admin/theme' },
];

function isActive(match: string): boolean {
    return currentPath.value.startsWith(match);
}
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Mobile drawer backdrop -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" @click="sidebarOpen = false"></div>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 w-64 transform border-r border-slate-200 bg-white transition-transform lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-700 text-sm font-semibold text-white">P</div>
                <span class="truncate text-sm font-semibold text-slate-800">Philanthropy Platform</span>
            </div>
            <nav class="space-y-0.5 px-3 py-4">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="isActive(item.match) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                >
                    <Icon :name="item.icon" class="h-4.5 w-4.5 shrink-0" />
                    {{ item.label }}
                </Link>
            </nav>
        </aside>

        <!-- Main column -->
        <div class="lg:pl-64">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6">
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                    aria-label="Open navigation"
                    @click="sidebarOpen = true"
                >
                    <Icon name="menu" class="h-5 w-5" />
                </button>
                <div class="min-w-0 flex-1">
                    <slot name="topbar" />
                </div>
                <div v-if="userEmail" class="flex items-center gap-2 text-sm text-slate-600">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                        <Icon name="user" class="h-4 w-4" />
                    </span>
                    <span class="hidden truncate sm:inline">{{ userEmail }}</span>
                </div>
            </header>

            <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-8">
                <div v-if="$slots.breadcrumb" class="mb-4">
                    <slot name="breadcrumb" />
                </div>
                <div class="mb-6">
                    <slot name="header" />
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
