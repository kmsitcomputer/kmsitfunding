<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Select from '../../UI/Select.vue';
import Button from '../../UI/Button.vue';

interface ContentOption {
    ulid: string;
    title: string | null;
}

const props = defineProps<{
    kind: string | null;
    contentUlid: string | null;
    pages: ContentOption[];
    articles: ContentOption[];
    titles: Record<string, string>;
    pagesHasMore: boolean;
    articlesHasMore: boolean;
    contentUrl: string;
    kindFieldId: string;
    itemFieldId: string;
    showKind: boolean;
}>();

const emit = defineEmits<{
    'update:kind': [value: string | null];
    'update:contentUlid': [value: string | null];
}>();

// SC-01 — per-kind pagination records. Page and Article state (items,
// page, hasMore, loading) are fully independent: a response is only ever
// applied to the record of the kind captured at request start, never routed
// via the live `isArticle` reactive after await.
interface KindPagination {
    items: ContentOption[];
    page: number;
    hasMore: boolean;
    loading: boolean;
}

const makeKindState = (initialItems: ContentOption[], initialHasMore: boolean): KindPagination => ({
    items: [...initialItems],
    page: 1,
    hasMore: initialHasMore,
    loading: false,
});

const pageState = ref<KindPagination>(makeKindState(props.pages, props.pagesHasMore));
const articleState = ref<KindPagination>(makeKindState(props.articles, props.articlesHasMore));

// SC-01 — selector generation. Bumped whenever parent props refresh the
// initial lists: any in-flight response from the previous generation is
// discarded instead of being merged into refreshed state.
const selectorGeneration = ref(0);

watch(
    () => props.pages,
    (value) => {
        selectorGeneration.value += 1;
        pageState.value = makeKindState(value, props.pagesHasMore);
    },
);
watch(
    () => props.articles,
    (value) => {
        selectorGeneration.value += 1;
        articleState.value = makeKindState(value, props.articlesHasMore);
    },
);
const kindModel = computed({
    get: () => props.kind ?? '',
    set: (value: string) => {
        emit('update:kind', value === '' ? null : value);
        emit('update:contentUlid', null);
    },
});

const isArticle = computed(() => props.kind === 'article');
const currentState = computed(() => (isArticle.value ? articleState.value : pageState.value));
const currentHasMore = computed(() => currentState.value.hasMore);
const currentLoading = computed(() => currentState.value.loading);

const itemsForKind = computed(() => {
    const base = currentState.value.items;
    const current = props.contentUlid;
    // RA-02: a title missing from `titles` means the actor is not
    // authorized to view that resource — show a neutral placeholder, never
    // the raw ULID (which would itself hint at private content) and never
    // a fetched title bypassing the server-side policy check.
    if (current && !base.some((item) => item.ulid === current)) {
        const label = props.titles[current];
        return [...base, { ulid: current, title: label ?? null }];
    }
    return base;
});

const itemModel = computed({
    get: () => props.contentUlid ?? '',
    set: (value: string) => {
        emit('update:contentUlid', value === '' ? null : value);
    },
});

const loadMore = async () => {
    // SC-01 — capture the ENTIRE request identity before the first await:
    // kind, generation, requested page, and the target state record. Nothing
    // below this point reads the live `kind`/`isArticle` reactive — the
    // response is applied to (or discarded from) the captured identity only.
    const requestKind = props.kind;
    const requestGeneration = selectorGeneration.value;
    // Capture the target record by reference for the in-flight loading flag,
    // but re-resolve the LIVE record before applying the response: a prop
    // refresh may have replaced the record object itself mid-flight, in
    // which case the response must land on (or be discarded from) current
    // state — never on a detached orphan.
    const inFlightState = requestKind === 'article' ? articleState.value : pageState.value;
    if (inFlightState.loading || !inFlightState.hasMore || !requestKind) return;
    inFlightState.loading = true;
    const requestedPage = inFlightState.page + 1;

    try {
        const url = `${props.contentUrl}?kind=${encodeURIComponent(requestKind)}&page=${requestedPage}`;
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const data = (await response.json()) as { items: ContentOption[]; hasMore: boolean };

        // SC-01 — stale-response guard: a kind switch, a prop refresh (which
        // bumps the generation), or any other reset invalidates this request.
        // Discard rather than merge into state that no longer belongs to it.
        if (selectorGeneration.value !== requestGeneration) return;

        const targetState = requestKind === 'article' ? articleState.value : pageState.value;

        // SC-01 (deduplication, non-gating): latest(updated_at) ordering can
        // move records between pages between requests — dedupe by canonical
        // ULID so options (and Vue keys) stay unique without reordering.
        const known = new Set(targetState.items.map((item) => item.ulid));
        const fresh = data.items.filter((item) => !known.has(item.ulid));
        targetState.items = [...targetState.items, ...fresh];
        targetState.page = requestedPage;
        targetState.hasMore = data.hasMore;
    } finally {
        inFlightState.loading = false;
    }
};
</script>

<template>
    <div class="space-y-3">
        <div v-if="showKind">
            <label :for="kindFieldId" class="mb-1 block text-sm font-medium text-slate-700">Content kind</label>
            <Select :id="kindFieldId" v-model="kindModel">
                <option value="">Select kind</option>
                <option value="page">Page</option>
                <option value="article">Article</option>
            </Select>
        </div>
        <div>
            <label :for="itemFieldId" class="mb-1 block text-sm font-medium text-slate-700">Content item</label>
            <Select :id="itemFieldId" v-model="itemModel" :disabled="showKind && !kind">
                <option value="">Select content</option>
                <option v-for="item in itemsForKind" :key="item.ulid" :value="item.ulid">{{ item.title ?? 'Restricted content' }}</option>
            </Select>
            <p v-if="showKind && !kind" class="mt-1 text-xs text-slate-500">Choose a content kind first.</p>
            <Button
                v-else-if="currentHasMore"
                type="button"
                variant="ghost"
                class="mt-2"
                :disabled="currentLoading"
                :aria-label="`Load more ${isArticle ? 'articles' : 'pages'}`"
                @click="loadMore"
            >
                {{ currentLoading ? 'Loading…' : 'Load more' }}
            </Button>
        </div>
    </div>
</template>
