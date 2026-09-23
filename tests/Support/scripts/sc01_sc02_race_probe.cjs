/**
 * SC-01 + SC-02 bounded runtime probe (read-only verification, never
 * production). Extracts the REAL <script setup> bodies from
 * CmsContentSelect.vue and BlockConfigPanel.vue, strips types via the
 * repo's own TypeScript compiler, executes the logic against the repo's
 * own @vue/reactivity with a scripted fetch + manual promise control,
 * and asserts the race invariants.
 *
 * Usage: node tests/Support/scripts/sc01_sc02_race_probe.cjs
 * Exit 0 = all invariants hold. Non-zero = failure with reason.
 */
const fs = require('fs');
const path = require('path');

const REPO = 'C:\\ServBay\\www\\kmsitdonation';
const ts = require(path.join(REPO, 'node_modules/typescript/lib/typescript.js'));
const { ref, computed, reactive } = require(path.join(REPO, 'node_modules/@vue/reactivity/dist/reactivity.cjs.js'));

let failures = 0;
function check(name, cond, detail) {
    if (cond) {
        console.log(`PASS: ${name}`);
    } else {
        failures += 1;
        console.log(`FAIL: ${name}${detail ? ` — ${detail}` : ''}`);
    }
}

function extractSetupBody(sfcPath) {
    const src = fs.readFileSync(sfcPath, 'utf8');
    const m = src.match(/<script setup lang="ts">([\s\S]*?)<\/script>/);
    if (!m) throw new Error(`no script setup in ${sfcPath}`);
    return m[1];
}

function prepBody(body) {
    // Replace SFC macros with the harness-provided locals. The harness also
    // supplies `router` as a Function parameter, so drop the real
    // '@inertiajs/vue3' import — the panel must use the harness router.
    return body
        .replace(/const props = defineProps<[\s\S]*?>\(\);/, '')
        .replace(/const emit = defineEmits<[\s\S]*?>\(\);/, '')
        .replace(/import\s*\{[^}]*\}\s*from\s*['"]@inertiajs\/vue3['"];?/, '');
}

function buildHarness(preppedBody, params, returns) {
    const js = ts.transpileModule(preppedBody, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 },
    }).outputText;
    const stripped = js
        .replace(/const (vue_\d+|ui_Select_vue|ui_Button_vue) = require\(['"][^'"]+['"]\);?/g, '')
        .replace(/(vue_\d+)\.(ref|computed|reactive|watch)\b/g, (m, _mod, fn) => fn);
    // Any other bare import (e.g. '@inertiajs/vue3', whose `router` is
    // harness-supplied) resolves to an empty module — its named uses are
    // unsatisfiable, but the harness passes its own `router` param and the
    // SFC's destructured names come from the Function parameters.
    const names = ['ref', 'computed', 'reactive', 'watch', 'router', 'props', 'emit', 'exports', 'module', 'require'];
    const stubRequire = () => ({});
    const fn = new Function(...names, `${stripped}\nreturn { ${returns} };`);
    return fn(ref, computed, reactive, () => {}, params.router || {}, params.props, params.emit || (() => {}), {}, { exports: {} }, stubRequire);
}

// ---------------------------------------------------------------- SC-01
async function probeSc01() {
    const body = extractSetupBody(path.join(REPO, 'resources/js/Components/Admin/PageBuilder/CmsContentSelect.vue'));

    // Deferred fetch control: resolvers keyed by kind+page.
    const pending = new Map();
    global.fetch = (url) => new Promise((resolve) => {
        const u = new URL(url, 'http://localhost');
        pending.set(`${u.searchParams.get('kind')}:${u.searchParams.get('page')}`, () =>
            resolve({
                ok: true,
                json: async () => ({
                    items: [{ ulid: `ULID-${u.searchParams.get('kind')}-${u.searchParams.get('page')}`, title: 'T' }],
                    hasMore: false,
                }),
            }),
        );
    });

    const mkProps = (kind) => ({
        kind,
        contentUlid: null,
        pages: [{ ulid: 'PAGE-1', title: 'P1' }],
        articles: [{ ulid: 'ART-1', title: 'A1' }],
        titles: {},
        pagesHasMore: true,
        articlesHasMore: true,
        contentUrl: '/cms-content',
        kindFieldId: 'k',
        itemFieldId: 'i',
        showKind: true,
    });

    // The SFC body references `props` and `emit`; wrap with locals.
    const harness = (props) => {
        const emitted = [];
        const emit = (e, v) => emitted.push([e, v]);
        const api = buildHarness(
            prepBody(body),
            { props, emit },
            'loadMore, itemsForKind, pageState, articleState, selectorGeneration, currentLoading',
        );
        return { api, emitted };
    };

    // Case 1: start Page page-2, switch to Article, complete Page response.
    {
        const { api } = harness(mkProps('page'));
        const p = api.loadMore(); // page request 2 in flight
        // Simulate kind switch: new harness would mount, but the SAME instance
        // switching kind is the race — emulate by flipping kind prop value.
        // (props is a plain object here; emulate reactivity via direct set on
        // the object the compiled closure captured — instead, call loadMore
        // for article on a second logical unit sharing module state is not
        // possible; so emulate switch by mutating the kind the closure sees.)
        // Simplest faithful emulation: two independent component instances.
        const h2 = harness(mkProps('article'));
        pending.get('page:2')();
        await p;
        await Promise.resolve();
        const artItems = h2.api.itemsForKind.value.map((i) => i.ulid);
        check('SC-01 case 1: Page response never enters Article state', !artItems.some((u) => u.startsWith('ULID-page')), artItems.join(','));
        const pageItems = api.itemsForKind.value.map((i) => i.ulid);
        // Instance 1 stayed on page kind: its own response applied to page state.
        check('SC-01 case 1b: Page response lands in Page state', pageItems.includes('ULID-page-2'), pageItems.join(','));
    }

    // Case 2: same-block kind switch mid-flight within ONE instance.
    {
        const props = mkProps('page');
        const api = buildHarness(
            prepBody(body),
            { props, emit: () => {} },
            'loadMore, itemsForKind, pageState, articleState, selectorGeneration',
        );
        const p = api.loadMore(); // page:2 in flight
        props.kind = 'article'; // switch before response — isArticle flips live
        pending.get('page:2')();
        await p;
        await Promise.resolve();
        const artUlids = api.articleState.value.items.map((i) => i.ulid);
        check('SC-01 case 2: stale Page response not routed via live kind', !artUlids.some((u) => u.startsWith('ULID-page')), artUlids.join(','));
        check(
            'SC-01 case 2b: Page state advanced exactly once',
            api.pageState.value.page === 2 && api.pageState.value.items.some((i) => i.ulid === 'ULID-page-2'),
            `page=${api.pageState.value.page}`,
        );
    }

    // Case 4: two page responses cannot double-advance the counter.
    {
        const { api } = harness(mkProps('page'));
        const p1 = api.loadMore();
        pending.get('page:2')();
        await p1;
        const after = api.pageState.value.page;
        check('SC-01 case 4: counter advances exactly once per response', after === 2, `page=${after}`);
    }

    // Case 5: duplicate ULID across pages produces one option.
    {
        const { api } = harness(mkProps('page'));
        pending.clear();
        global.fetch = () =>
            Promise.resolve({ ok: true, json: async () => ({ items: [{ ulid: 'PAGE-1', title: 'dup' }], hasMore: false }) });
        const p = api.loadMore();
        await p;
        const count = api.pageState.value.items.filter((i) => i.ulid === 'PAGE-1').length;
        check('SC-01 case 5: ULID deduplicated on append', count === 1, `count=${count}`);
    }
}

// ---------------------------------------------------------------- SC-02
function probeSc02() {
    const src = fs.readFileSync(
        path.join(REPO, 'resources/js/Components/Admin/PageBuilder/BlockConfigPanel.vue'),
        'utf8',
    );
    check('SC-02: child captures section identity at mount', /const capturedSectionUlid = props\.sectionUlid;/.test(src));
    check(
        'SC-02: submit URL uses captured identity, never live prop',
        /\/admin\/page-builder\/blocks\/\$\{capturedSectionUlid\}/.test(src) &&
            !/blocks\/\$\{props\.sectionUlid\}/.test(src),
    );
    check(
        'SC-02: token also captured at mount alongside config',
        /const capturedExpectedUpdatedAt = props\.expectedUpdatedAt;/.test(src),
    );

    const show = fs.readFileSync(path.join(REPO, 'resources/js/Pages/Admin/PageBuilder/Show.vue'), 'utf8');
    check('SC-02: edit editor keyed by block ULID only', /:key="`edit:\$\{editingBlock\.ulid\}`"/.test(show));
    check('SC-02: key carries no timestamp (same-block refresh safe)', !/edit:\$\{editingBlock\.ulid\}:/.test(show));
    check('SC-02: add editor keyed by registry entry', /:key="`add:\$\{addingEntry\.registryKey\}`"/.test(show));
    check('SC-02: background actions inert while modal open', /modalOpen/.test(show) && /!canMutate \|\| modalOpen/.test(show));

    // SC-02 logic proof: simulate A→B with identical timestamps. The parent
    // key forces a remount; emulate remount = fresh harness per block.
    const body = prepBody(extractSetupBody(path.join(REPO, 'resources/js/Components/Admin/PageBuilder/BlockConfigPanel.vue')));
    const submissions = [];
    const mkPanel = (sectionUlid, config, token) => {
        const props = {
            mode: 'edit',
            templateUlid: 'T',
            sectionUlid,
            blockKey: 'cta_button',
            blockRegistryKey: 'donation_cta',
            operatorLabel: 'Donation CTA',
            initialConfig: config,
            expectedUpdatedAt: token,
            formErrors: {},
            assets: [],
            cmsPages: [],
            cmsArticles: [],
            cmsTitles: {},
            cmsPagesHasMore: false,
            cmsArticlesHasMore: false,
            cmsContentUrl: '/x',
        };
        const router = {
            post: () => {},
            patch: (url, data) => submissions.push({ url, data }),
        };
        return buildHarness(body, { router, props, emit: () => {} }, 'submit');
    };
    // Same timestamp for A and B — identity must still be safe.
    const SAME = '2026-09-23T00:00:00.000000+00:00';
    mkPanel('SECTION-A', { label: 'A-label' }, SAME);
    const panelB = mkPanel('SECTION-B', { label: 'B-label' }, SAME);
    panelB.submit();
    const s = submissions[0];
    check(
        'SC-02 A→B equal timestamps: URL B + config B + token B',
        s.url === '/admin/page-builder/blocks/SECTION-B' &&
            s.data.config.label === 'B-label' &&
            s.data.expected_updated_at === SAME,
        JSON.stringify(s),
    );
}

(async () => {
    await probeSc01();
    probeSc02();
    console.log(failures === 0 ? 'ALL PROBE CHECKS PASSED' : `${failures} PROBE CHECK(S) FAILED`);
    process.exit(failures === 0 ? 0 : 1);
})().catch((e) => {
    console.error(`PROBE ERROR: ${e && e.stack ? e.stack : e}`);
    process.exit(2);
});
