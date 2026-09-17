<script setup lang="ts">
import {
    AlertTriangle,
    Calendar,
    Check,
    ChevronDown,
    ChevronRight,
    ExternalLink,
    FileText,
    Image,
    LayoutDashboard,
    LogOut,
    Megaphone,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    Palette,
    Plus,
    Target,
    Trash2,
    Upload,
    User,
    Wallet,
    X,
} from '@lucide/vue';
import { computed } from 'vue';

/**
 * Thin name-based wrapper over @lucide/vue (the project's chosen icon
 * library — added during the presentation-foundation remediation pass,
 * replacing an earlier hand-written inline-SVG set that predated any
 * icon-library decision). Kept as a wrapper, not direct per-call-site
 * imports, so every existing `<Icon name="...">` call site continues to
 * work unchanged.
 */
const registry = {
    dashboard: LayoutDashboard,
    document: FileText,
    megaphone: Megaphone,
    wallet: Wallet,
    palette: Palette,
    menu: Menu,
    close: X,
    chevronDown: ChevronDown,
    chevronRight: ChevronRight,
    user: User,
    check: Check,
    upload: Upload,
    trash: Trash2,
    plus: Plus,
    image: Image,
    calendar: Calendar,
    target: Target,
    warning: AlertTriangle,
    external: ExternalLink,
    collapse: PanelLeftClose,
    expand: PanelLeftOpen,
    logout: LogOut,
};

const props = defineProps<{ name: keyof typeof registry | string; class?: string }>();

const component = computed(() => registry[props.name as keyof typeof registry] ?? FileText);
</script>

<template>
    <component :is="component" :class="class" aria-hidden="true" />
</template>
