<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from '../UI/Icon.vue';

/**
 * The reusable admin application shell for this codebase (no equivalent
 * existed after IMP-005/006 — confirmed by repository audit). Deliberately
 * generic/reusable, not coupled to Campaign business logic. Navigation is
 * presentation only — every link still lands on a backend route enforcing
 * its own Policy; this sidebar grants nothing by itself, and hiding a
 * group is never a substitute for backend authorization.
 */
const page = usePage();
const userEmail = computed(() => (page.props.auth as { user: { email: string } | null } | undefined)?.user?.email ?? null);
const currentPath = computed(() => page.url.split('?')[0]);

const mobileOpen = ref(false);
const collapsed = ref(false);
const userMenuOpen = ref(false);
const userMenuRoot = ref<HTMLElement | null>(null);

function handleDocumentClick(event: MouseEvent) {
    if (userMenuOpen.value && userMenuRoot.value && !userMenuRoot.value.contains(event.target as Node)) {
        userMenuOpen.value = false;
    }
}

onMounted(() => {
    try {
        collapsed.value = localStorage.getItem('admin.sidebar.collapsed') === '1';
    } catch {
        // Private-window/blocked storage — default to expanded, no crash.
    }
    document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
});

function toggleCollapsed() {
    collapsed.value = !collapsed.value;
    try {
        localStorage.setItem('admin.sidebar.collapsed', collapsed.value ? '1' : '0');
    } catch {
        // Per-viewer convenience only — safe to silently no-op.
    }
}

interface NavItem {
    label: string;
    href: string;
    icon: string;
    match: string;
}

interface NavGroup {
    label: string;
    items: NavItem[];
}

// Only implemented, routed, authorized modules are listed — nothing here
// represents a future/unimplemented IMP.
const navGroups: NavGroup[] = [
    { label: 'Overview', items: [{ label: 'Dashboard', href: '/dashboard', icon: 'dashboard', match: '/dashboard' }] },
    {
        label: 'Content',
        items: [
            { label: 'Pages', href: '/admin/content/pages', icon: 'document', match: '/admin/content/pages' },
            { label: 'Articles', href: '/admin/content/articles', icon: 'document', match: '/admin/content/articles' },
            { label: 'Media', href: '/admin/content/media', icon: 'image', match: '/admin/content/media' },
            { label: 'Homepage', href: '/admin/content/homepage', icon: 'target', match: '/admin/content/homepage' },
        ],
    },
    {
        label: 'Fundraising',
        items: [
            { label: 'Programs', href: '/admin/campaign/programs', icon: 'megaphone', match: '/admin/campaign/programs' },
            { label: 'Campaigns', href: '/admin/campaign/campaigns', icon: 'megaphone', match: '/admin/campaign/campaigns' },
            { label: 'Funds', href: '/admin/campaign/funds', icon: 'wallet', match: '/admin/campaign/funds' },
        ],
    },
    { label: 'Presentation', items: [{ label: 'Theme', href: '/admin/theme', icon: 'palette', match: '/admin/theme' }] },
];

function isActive(match: string): boolean {
    return currentPath.value.startsWith(match);
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" @click="mobileOpen = false"></div>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex flex-col border-r border-slate-200 bg-white transition-all lg:translate-x-0"
            :class="[mobileOpen ? 'translate-x-0' : '-translate-x-full', collapsed ? 'lg:w-[4.5rem]' : 'lg:w-64', 'w-64']"
        >
            <div class="flex h-16 shrink-0 items-center gap-2 border-b border-slate-200 px-4">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-700 text-sm font-semibold text-white">P</div>
                <span v-if="!collapsed" class="truncate text-sm font-semibold text-slate-800 lg:inline">Philanthropy Platform</span>
                <span class="truncate text-sm font-semibold text-slate-800 lg:hidden">Philanthropy Platform</span>
            </div>

            <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
                <div v-for="group in navGroups" :key="group.label">
                    <p v-if="!collapsed" class="mb-1.5 px-3 text-xs font-semibold uppercase tracking-wide text-slate-400 lg:block" :class="{ 'lg:hidden': collapsed }">
                        {{ group.label }}
                    </p>
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            :title="item.label"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="[
                                isActive(item.match) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                                collapsed ? 'lg:justify-center' : '',
                            ]"
                        >
                            <Icon :name="item.icon" class="h-4.5 w-4.5 shrink-0" />
                            <span :class="collapsed ? 'lg:hidden' : ''">{{ item.label }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <button
                type="button"
                class="hidden shrink-0 items-center gap-2 border-t border-slate-200 px-4 py-3 text-sm text-slate-500 hover:bg-slate-50 lg:flex"
                :class="collapsed ? 'justify-center' : ''"
                :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                @click="toggleCollapsed"
            >
                <Icon :name="collapsed ? 'expand' : 'collapse'" class="h-4.5 w-4.5 shrink-0" />
                <span v-if="!collapsed">Collapse</span>
            </button>
        </aside>

        <!-- Main column -->
        <div class="transition-all" :class="collapsed ? 'lg:pl-[4.5rem]' : 'lg:pl-64'">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6">
                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Open navigation" @click="mobileOpen = true">
                    <Icon name="menu" class="h-5 w-5" />
                </button>
                <div class="min-w-0 flex-1">
                    <slot name="topbar" />
                </div>
                <div v-if="userEmail" ref="userMenuRoot" class="relative">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-100"
                        aria-haspopup="menu"
                        :aria-expanded="userMenuOpen"
                        @click="userMenuOpen = !userMenuOpen"
                    >
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                            <Icon name="user" class="h-4 w-4" />
                        </span>
                        <span class="hidden truncate sm:inline">{{ userEmail }}</span>
                        <Icon name="chevronDown" class="h-3.5 w-3.5 text-slate-400" />
                    </button>
                    <div
                        v-if="userMenuOpen"
                        class="absolute right-0 z-40 mt-1.5 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                        role="menu"
                    >
                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50" role="menuitem" @click="logout">
                            <Icon name="logout" class="h-4 w-4" />
                            Log out
                        </button>
                    </div>
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
