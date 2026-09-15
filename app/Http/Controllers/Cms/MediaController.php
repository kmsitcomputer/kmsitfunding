<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\UpdateMediaRequest;
use App\Http\Requests\Cms\UploadMediaRequest;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Rbac\Principal;
use App\Policies\MediaPolicy;
use App\Services\Content\Exceptions\MediaValidationException;
use App\Services\Content\MediaService;
use App\Services\Content\MediaTokenResolver;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-005 admin UI (slice 23) — Media library. Thin, mirroring
 * PageController's own conventions: resolve the acting Principal, authorize
 * via MediaPolicy, delegate to MediaService, surface its exceptions as
 * validation-style errors.
 */
class MediaController extends Controller
{
    public function index(Request $request, MediaPolicy $policy, MediaTokenResolver $resolver): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->upload($actor), 403);

        $assets = CmsMediaAsset::query()->latest('id')->paginate(24);
        $assets->getCollection()->transform(function (CmsMediaAsset $asset) use ($resolver) {
            $asset->url = $resolver->resolveUrl($asset->ulid);

            return $asset;
        });

        return Inertia::render('Cms/Media/Index', [
            'assets' => $assets,
        ]);
    }

    public function store(UploadMediaRequest $request, MediaPolicy $policy, MediaService $mediaService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->upload($actor), 403);

        try {
            $mediaService->upload($request->file('file'), $actor, $request->safe()->only(['alt_text', 'caption']));
        } catch (MediaValidationException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return redirect()->route('cms.media.index')->with('status', 'media-uploaded');
    }

    public function update(UpdateMediaRequest $request, MediaPolicy $policy, CmsMediaAsset $asset, MediaService $mediaService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $asset), 403);

        $mediaService->updateMetadata($asset, $request->validated(), $actor);

        return redirect()->route('cms.media.index')->with('status', 'media-updated');
    }

    public function archive(Request $request, MediaPolicy $policy, CmsMediaAsset $asset, MediaService $mediaService): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->archive($actor, $asset), 403);

        try {
            $mediaService->archive($asset, $actor);
        } catch (MediaValidationException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return redirect()->route('cms.media.index')->with('status', 'media-archived');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
