<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';

defineProps<{ items: Array<{ label: string; href?: string }> }>();
</script>

<template>
    <nav aria-label="Breadcrumb" class="flex items-center text-sm text-slate-500">
        <template v-for="(item, i) in items" :key="i">
            <Link v-if="item.href" :href="item.href" class="hover:text-slate-700">{{ item.label }}</Link>
            <span v-else class="font-medium text-slate-700">{{ item.label }}</span>
            <Icon v-if="i < items.length - 1" name="chevronRight" class="mx-1.5 h-3.5 w-3.5 text-slate-300" />
        </template>
    </nav>
</template>
