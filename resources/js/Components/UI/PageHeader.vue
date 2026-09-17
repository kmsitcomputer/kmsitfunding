<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="truncate text-xl font-semibold text-slate-900">{{ title }}</h1>
                <slot name="badge" />
            </div>
            <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
            <div v-if="$slots.meta" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                <slot name="meta" />
            </div>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
