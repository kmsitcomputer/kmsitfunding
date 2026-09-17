<?php

namespace App\Http\Controllers;

use App\Services\Content\ContentResolverService;
use App\Services\Content\HomepageContentResolver;
use App\Services\Theme\PublicRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * IMP-006 — the public rendering pipeline IMP-005 §8 described but
 * explicitly left unbuilt (docs/implementation/IMP-006-theme-engine.md
 * section 13). Consumes IMP-005's read contracts only; never writes to any
 * cms_* table.
 */
class PublicContentController extends Controller
{
    public function home(HomepageContentResolver $homepageResolver, PublicRenderer $renderer)
    {
        $page = $homepageResolver->resolve();

        $content = $page !== null ? $renderer->toPublishedContent($page) : null;
        $render = $renderer->renderForContentKind('home', $content);

        return Inertia::render('Public/ThemeRender', [
            'content' => $content,
            'template' => $render,
        ]);
    }

    public function show(Request $request, ContentResolverService $contentResolver, PublicRenderer $renderer)
    {
        $resolution = $contentResolver->resolve($request->path());

        if ($resolution->type === 'redirect') {
            return redirect($resolution->redirectTo, 301);
        }

        if ($resolution->type === 'not_found') {
            $render = $renderer->renderForContentKind('not_found');

            return Inertia::render('Public/NotFound', [
                'template' => $render,
            ])->toResponse($request)->setStatusCode(404);
        }

        $content = $resolution->content;
        $render = $renderer->renderForContentKind($content->kind, $content);

        return Inertia::render('Public/ThemeRender', [
            'content' => $content,
            'template' => $render,
        ]);
    }
}
