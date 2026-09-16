<script setup lang="ts">
/**
 * Minimal inline-SVG icon set — no new npm icon dependency introduced,
 * per the standing "don't add a dependency merely for icons" constraint.
 * Paths are simple, original 24x24 outline glyphs.
 */
const paths: Record<string, string> = {
    dashboard: 'M4 4h7v9H4V4Zm9 0h7v5h-7V4Zm0 9h7v7h-7v-7ZM4 17h7v3H4v-3Z',
    document: 'M6 3h9l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm8 1.5V9h4.5',
    megaphone: 'M3 10v4a1 1 0 0 0 1 1h2l4 4v-14l-4 4H4a1 1 0 0 0-1 1Zm14-3.5a5 5 0 0 1 0 7',
    wallet: 'M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1h-9a3 3 0 0 0-3 3v6a2 2 0 0 1-2-2V7Zm5 3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1v-7Zm8 3.5a1.25 1.25 0 1 0 0 .01Z',
    palette: 'M12 3a9 9 0 1 0 0 18c1.5 0 2-1 2-2s-.5-1.5-1-2 .3-2 1.5-2H16a4 4 0 0 0 4-4 8 8 0 0 0-8-8Zm-4.5 6a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm3-3a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm5 1a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm2 4a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Z',
    menu: 'M4 6h16M4 12h16M4 18h16',
    close: 'M6 6l12 12M18 6L6 18',
    chevronDown: 'M6 9l6 6 6-6',
    chevronRight: 'M9 6l6 6-6 6',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0',
    check: 'M5 13l4 4L19 7',
    upload: 'M12 16V4m0 0L7 9m5-5l5 5M5 20h14',
    trash: 'M6 7h12M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-8 0 1 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-12',
    plus: 'M12 5v14M5 12h14',
    image: 'M4 5h16v14H4V5Zm2 11 4-4 3 3 3-4 4 5H6Z',
    calendar: 'M6 3v3M18 3v3M4 8h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z',
    target: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-4a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0-3a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
    warning: 'M12 3 2 20h20L12 3Zm0 6v5m0 3h.01',
    external: 'M14 5h5v5M19 5l-9 9M8 5H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2',
};

defineProps<{ name: keyof typeof paths | string; class?: string }>();
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" :class="class" aria-hidden="true">
        <path :d="paths[name] ?? ''" />
    </svg>
</template>
