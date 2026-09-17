<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\PublishPageRequest;
use App\Http\Requests\Cms\StorePageRequest;
use App\Http\Requests\Cms\UpdatePageRequest;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Policies\ContentPagePolicy;
use App\Services\Content\Exceptions\ContentSanitizationException;
use App\Services\Content\Exceptions\DraftEditConflictException;
use App\Services\Content\Exceptions\PathConflictException;
use App\Services\Content\Exceptions\PathValidationException;
use App\Services\Content\Exceptions\PublicationValidationException;
use App\Services\Content\Exceptions\RevisionValidationException;
use App\Services\Content\PageService;
use App\Services\Content\PublicationService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-005 admin UI (slice 21) — Page management. Thin: resolve the acting
 * Principal, authorize via ContentPagePolicy, delegate to PageService /
 * PublicationService, surface their exceptions as validation-style errors.
 * No business logic lives here (section 18's own note: "Controllers stay
 * thin so IMP-025 can bind to the same services without new business
 * logic").
 */
class PageController extends Controller
{
    public function index(Request $request, ContentPagePolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->view($actor), 403);

        $pages = CmsPage::query()->latest('updated_at')->paginate(20);

        return Inertia::render('Cms/Pages/Index', [
            'pages' => $pages,
        ]);
    }

    public function create(Request $request, ContentPagePolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        return Inertia::render('Cms/Pages/Create');
    }

    public function store(StorePageRequest $request, ContentPagePolicy $policy, PageService $pageService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        try {
            $page = $pageService->create($request->validated(), $actor);
        } catch (ContentSanitizationException $e) {
            throw ValidationException::withMessages(['body_html' => $e->getMessage()]);
        } catch (RevisionValidationException $e) {
            throw ValidationException::withMessages(['body_html' => $e->getMessage()]);
        }

        return redirect()->route('cms.pages.edit', $page)->with('status', 'page-created');
    }

    public function edit(Request $request, ContentPagePolicy $policy, CmsPage $page): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewPage($actor, $page), 403);

        return Inertia::render('Cms/Pages/Edit', [
            'page' => $page,
            'draft' => $page->currentDraft()->first(),
            'published' => $page->publishedRevision,
        ]);
    }

    public function update(UpdatePageRequest $request, ContentPagePolicy $policy, CmsPage $page, PageService $pageService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $page), 403);

        $payload = $request->safe()->except('expected_edit_version');

        try {
            $pageService->update($page, $payload, (int) $request->validated('expected_edit_version'), $actor);
        } catch (DraftEditConflictException $e) {
            throw ValidationException::withMessages(['expected_edit_version' => 'This draft was changed by someone else — reload and try again.']);
        } catch (ContentSanitizationException $e) {
            throw ValidationException::withMessages(['body_html' => $e->getMessage()]);
        } catch (RevisionValidationException $e) {
            throw ValidationException::withMessages(['body_html' => $e->getMessage()]);
        }

        return redirect()->route('cms.pages.edit', $page)->with('status', 'page-updated');
    }

    public function publish(PublishPageRequest $request, ContentPagePolicy $policy, CmsPage $page, PublicationService $publicationService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->publish($actor, $page), 403);

        $candidate = $page->currentDraft()->first() ?? $page->publishedRevision;

        if ($candidate === null) {
            throw ValidationException::withMessages(['path' => 'This page has no draft or published revision to publish.']);
        }

        try {
            $publicationService->publish($page, $candidate, $actor, $request->validated('path'));
        } catch (PathConflictException|PathValidationException|PublicationValidationException $e) {
            throw ValidationException::withMessages(['path' => $e->getMessage()]);
        }

        return redirect()->route('cms.pages.edit', $page)->with('status', 'page-published');
    }

    public function unpublish(Request $request, ContentPagePolicy $policy, CmsPage $page, PublicationService $publicationService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->publish($actor, $page), 403);

        try {
            $publicationService->unpublish($page, $actor);
        } catch (PublicationValidationException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return redirect()->route('cms.pages.edit', $page)->with('status', 'page-unpublished');
    }

    public function archive(Request $request, ContentPagePolicy $policy, CmsPage $page, PublicationService $publicationService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->archive($actor, $page), 403);

        try {
            $publicationService->archive($page, $actor);
        } catch (PublicationValidationException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return redirect()->route('cms.pages.index')->with('status', 'page-archived');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
