<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\SetHomepageRequest;
use App\Models\Cms\CmsHomepageAssignment;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Policies\ContentPagePolicy;
use App\Services\Content\Exceptions\HomepageAssignmentConflictException;
use App\Services\Content\Exceptions\PublicationValidationException;
use App\Services\Content\PublicationService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-005 admin UI (slice 24) — the singleton homepage designation (section
 * 26 flow 7). Gated by ContentPagePolicy::assignHomepage() (content.publish,
 * GLOBAL — see that method's doc comment), never a page-specific policy
 * method: there is exactly one designation slot, not a per-page capability.
 */
class HomepageController extends Controller
{
    public function edit(Request $request, ContentPagePolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->assignHomepage($actor), 403);

        $assignment = CmsHomepageAssignment::query()->with('page')->findOrFail(1);

        $eligiblePages = CmsPage::query()
            ->where('status', '!=', 'ARCHIVED')
            ->orderBy('title')
            ->get(['id', 'ulid', 'title', 'status']);

        return Inertia::render('Cms/Homepage/Edit', [
            'assignment' => $assignment,
            'eligiblePages' => $eligiblePages,
        ]);
    }

    public function update(SetHomepageRequest $request, ContentPagePolicy $policy, PublicationService $publicationService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->assignHomepage($actor), 403);

        $targetPage = $request->validated('page_ulid') !== null
            ? CmsPage::where('ulid', $request->validated('page_ulid'))->firstOrFail()
            : null;

        $expectedPageId = $request->validated('expected_page_ulid') !== null
            ? CmsPage::where('ulid', $request->validated('expected_page_ulid'))->value('id')
            : null;

        try {
            $publicationService->setHomepage($targetPage, $actor, $expectedPageId);
        } catch (HomepageAssignmentConflictException $e) {
            throw ValidationException::withMessages(['page_ulid' => 'The homepage designation was changed by someone else — reload and try again.']);
        } catch (PublicationValidationException $e) {
            throw ValidationException::withMessages(['page_ulid' => $e->getMessage()]);
        }

        return redirect()->route('cms.homepage.edit')->with('status', 'homepage-updated');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
