<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\PublishArticleRequest;
use App\Http\Requests\Cms\StoreArticleRequest;
use App\Http\Requests\Cms\UpdateArticleRequest;
use App\Models\Cms\CmsArticle;
use App\Models\Rbac\Principal;
use App\Policies\ContentArticlePolicy;
use App\Services\Content\ArticleService;
use App\Services\Content\Exceptions\ContentSanitizationException;
use App\Services\Content\Exceptions\DraftEditConflictException;
use App\Services\Content\Exceptions\PathConflictException;
use App\Services\Content\Exceptions\PathValidationException;
use App\Services\Content\Exceptions\PublicationValidationException;
use App\Services\Content\Exceptions\RevisionValidationException;
use App\Services\Content\PublicationService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-005 admin UI (slice 22) — Article management. Mirrors PageController
 * exactly (see its doc comment) — the only difference is the owner type and
 * the additional `article_type` field (Q33/HD-IMP005-05: News is Article
 * classification, never a separate entity or controller).
 */
class ArticleController extends Controller
{
    public function index(Request $request, ContentArticlePolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->view($actor), 403);

        $articles = CmsArticle::query()->latest('updated_at')->paginate(20);

        return Inertia::render('Cms/Articles/Index', [
            'articles' => $articles,
        ]);
    }

    public function create(Request $request, ContentArticlePolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        return Inertia::render('Cms/Articles/Create');
    }

    public function store(StoreArticleRequest $request, ContentArticlePolicy $policy, ArticleService $articleService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        try {
            $article = $articleService->create($request->validated(), $actor);
        } catch (ContentSanitizationException|RevisionValidationException $e) {
            throw ValidationException::withMessages(['body_html' => $e->getMessage()]);
        }

        return redirect()->route('cms.articles.edit', $article)->with('status', 'article-created');
    }

    public function edit(Request $request, ContentArticlePolicy $policy, CmsArticle $article): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewArticle($actor, $article), 403);

        return Inertia::render('Cms/Articles/Edit', [
            'article' => $article,
            'draft' => $article->currentDraft()->first(),
            'published' => $article->publishedRevision,
        ]);
    }

    public function update(UpdateArticleRequest $request, ContentArticlePolicy $policy, CmsArticle $article, ArticleService $articleService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $article), 403);

        $payload = $request->safe()->except('expected_edit_version');

        try {
            $articleService->update($article, $payload, (int) $request->validated('expected_edit_version'), $actor);
        } catch (DraftEditConflictException $e) {
            throw ValidationException::withMessages(['expected_edit_version' => 'This draft was changed by someone else — reload and try again.']);
        } catch (ContentSanitizationException|RevisionValidationException $e) {
            throw ValidationException::withMessages(['body_html' => $e->getMessage()]);
        }

        return redirect()->route('cms.articles.edit', $article)->with('status', 'article-updated');
    }

    public function publish(PublishArticleRequest $request, ContentArticlePolicy $policy, CmsArticle $article, PublicationService $publicationService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->publish($actor, $article), 403);

        $candidate = $article->currentDraft()->first() ?? $article->publishedRevision;

        if ($candidate === null) {
            throw ValidationException::withMessages(['path' => 'This article has no draft or published revision to publish.']);
        }

        try {
            $publicationService->publish($article, $candidate, $actor, $request->validated('path'));
        } catch (PathConflictException|PathValidationException|PublicationValidationException $e) {
            throw ValidationException::withMessages(['path' => $e->getMessage()]);
        }

        return redirect()->route('cms.articles.edit', $article)->with('status', 'article-published');
    }

    public function unpublish(Request $request, ContentArticlePolicy $policy, CmsArticle $article, PublicationService $publicationService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->publish($actor, $article), 403);

        try {
            $publicationService->unpublish($article, $actor);
        } catch (PublicationValidationException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return redirect()->route('cms.articles.edit', $article)->with('status', 'article-unpublished');
    }

    public function archive(Request $request, ContentArticlePolicy $policy, CmsArticle $article, PublicationService $publicationService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->archive($actor, $article), 403);

        try {
            $publicationService->archive($article, $actor);
        } catch (PublicationValidationException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return redirect()->route('cms.articles.index')->with('status', 'article-archived');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
