<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailChangeController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Campaign\CampaignController as AdminCampaignController;
use App\Http\Controllers\Campaign\FundController;
use App\Http\Controllers\Campaign\ProgramController as AdminProgramController;
use App\Http\Controllers\Cms\ArticleController;
use App\Http\Controllers\Cms\HomepageController;
use App\Http\Controllers\Cms\MediaController;
use App\Http\Controllers\Cms\PageController;
use App\Http\Controllers\PublicCampaignController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\PublicProgramController;
use App\Http\Controllers\Theme\ThemeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// IMP-006 — the homepage entry point (docs/implementation/
// IMP-006-theme-engine.md section 13). Distinct, first-registered route,
// never shadowed by the catch-all below.
Route::get('/', [PublicContentController::class, 'home'])->name('home');

// IMP-002 — Identity + Authentication. Same-origin, single root domain — no
// separate auth subdomain. See
// docs/implementation/IMP-002-identity-authentication.md "Route Ownership".

// Guest authentication.
Route::middleware('guest')->group(function () {
    Route::get('/donor/register', [RegisteredUserController::class, 'create'])->name('donor.register');
    Route::get('/fundraiser/register', [RegisteredUserController::class, 'create'])->name('fundraiser.register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/mfa/challenge', [MfaChallengeController::class, 'create'])->name('mfa.challenge.create');
    Route::post('/mfa/challenge', [MfaChallengeController::class, 'store'])->name('mfa.challenge.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');

    // Invitation acceptance (Partner Representative / Internal Administrative
    // Identity / Super Admin — Q22). No public self-registration for these
    // actors; acceptance is the only way in.
    Route::get('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
    Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'store'])->name('invitations.accept.store');
});

// Authenticated account security.
Route::middleware(['auth', 'identity.active'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->name('verification.send');

    Route::put('/account/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('/account/confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('/account/confirm-password', [ConfirmablePasswordController::class, 'store'])->name('password.confirm.store');

    Route::post('/account/email', [EmailChangeController::class, 'store'])->name('email-change.request');
    Route::get('/account/email/{emailChangeRequest}/verify', [EmailChangeController::class, 'verify'])->name('email-change.verify');

    // MFA enrollment does not itself require ELEVATED (it requires a valid
    // TOTP proof of possession, which is its own strong factor); disable/reset
    // does require ELEVATED, per "MFA > Reset / Disable".
    Route::get('/account/mfa/enroll', [MfaController::class, 'enroll'])->name('mfa.enroll');
    Route::post('/account/mfa/confirm', [MfaController::class, 'confirm'])->name('mfa.confirm');

    // ELEVATED step-up via a fresh TOTP challenge — the second mechanism
    // (alongside password confirmation) that can earn ELEVATED assurance.
    Route::post('/account/mfa/elevate', [MfaController::class, 'elevate'])->name('mfa.elevate');

    Route::middleware('elevated.assurance')->group(function () {
        Route::delete('/account/mfa', [MfaController::class, 'disable'])->name('mfa.disable');
        Route::post('/account/mfa/reset', [MfaController::class, 'reset'])->name('mfa.reset');
        Route::post('/account/mfa/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes'])->name('mfa.recovery-codes.regenerate');
    });

    // IMP-005 — CMS admin UI (section 24: CMS admin routes live under
    // /admin/content/*). {page} is bound by ULID (CmsPage::getRouteKeyName()),
    // never the internal BIGINT id.
    Route::prefix('admin/content/pages')->name('cms.pages.')->group(function () {
        Route::get('/', [PageController::class, 'index'])->name('index');
        Route::get('/create', [PageController::class, 'create'])->name('create');
        Route::post('/', [PageController::class, 'store'])->name('store');
        Route::get('/{page}', [PageController::class, 'edit'])->name('edit');
        Route::patch('/{page}', [PageController::class, 'update'])->name('update');
        Route::post('/{page}/publish', [PageController::class, 'publish'])->name('publish');
        Route::post('/{page}/unpublish', [PageController::class, 'unpublish'])->name('unpublish');
        Route::post('/{page}/archive', [PageController::class, 'archive'])->name('archive');
    });

    // IMP-005 — Article admin UI (slice 22), mirroring the Page routes above.
    // News is Article classification (Q33/HD-IMP005-05), not a separate route
    // group.
    Route::prefix('admin/content/articles')->name('cms.articles.')->group(function () {
        Route::get('/', [ArticleController::class, 'index'])->name('index');
        Route::get('/create', [ArticleController::class, 'create'])->name('create');
        Route::post('/', [ArticleController::class, 'store'])->name('store');
        Route::get('/{article}', [ArticleController::class, 'edit'])->name('edit');
        Route::patch('/{article}', [ArticleController::class, 'update'])->name('update');
        Route::post('/{article}/publish', [ArticleController::class, 'publish'])->name('publish');
        Route::post('/{article}/unpublish', [ArticleController::class, 'unpublish'])->name('unpublish');
        Route::post('/{article}/archive', [ArticleController::class, 'archive'])->name('archive');
    });

    // IMP-005 — Media library admin UI (slice 23).
    Route::prefix('admin/content/media')->name('cms.media.')->group(function () {
        Route::get('/', [MediaController::class, 'index'])->name('index');
        Route::post('/', [MediaController::class, 'store'])->name('store');
        Route::patch('/{asset}', [MediaController::class, 'update'])->name('update');
        Route::post('/{asset}/archive', [MediaController::class, 'archive'])->name('archive');
    });

    // IMP-005 — homepage designation admin UI (slice 24). Singleton, no
    // {page} in the URL — see HomepageController's own doc comment.
    Route::prefix('admin/content/homepage')->name('cms.homepage.')->group(function () {
        Route::get('/', [HomepageController::class, 'edit'])->name('edit');
        Route::patch('/', [HomepageController::class, 'update'])->name('update');
    });

    // IMP-006 — Theme Engine admin UI (docs/implementation/
    // IMP-006-theme-engine.md). {theme}/{template}/{section}/{component}/
    // {menu}/{item}/{asset} are bound by ULID, never the internal BIGINT id.
    Route::prefix('admin/theme')->name('theme.')->group(function () {
        Route::get('/', [ThemeController::class, 'index'])->name('index');
        Route::post('/', [ThemeController::class, 'store'])->name('store');
        Route::get('/{theme}', [ThemeController::class, 'show'])->name('show');
        Route::patch('/{theme}', [ThemeController::class, 'update'])->name('update');
        Route::post('/{theme}/activate', [ThemeController::class, 'activate'])->name('activate');
        Route::post('/{theme}/archive', [ThemeController::class, 'archive'])->name('archive');

        Route::post('/{theme}/templates', [ThemeController::class, 'storeTemplate'])->name('templates.store');
        Route::post('/templates/{template}/sections', [ThemeController::class, 'createSection'])->name('sections.create');
        Route::post('/sections/{section}/components', [ThemeController::class, 'createComponent'])->name('components.create');
        Route::patch('/components/{component}', [ThemeController::class, 'updateComponent'])->name('components.update');
        Route::delete('/components/{component}', [ThemeController::class, 'deleteComponent'])->name('components.delete');
        Route::post('/templates/{template}/sections/reorder', [ThemeController::class, 'reorderSections'])->name('sections.reorder');

        Route::post('/{theme}/navigation-menus', [ThemeController::class, 'createMenu'])->name('navigation.menus.create');
        Route::post('/navigation-menus/{menu}/items', [ThemeController::class, 'createNavigationItem'])->name('navigation.items.create');
        Route::delete('/navigation-items/{item}', [ThemeController::class, 'deleteNavigationItem'])->name('navigation.items.delete');

        Route::post('/{theme}/branding', [ThemeController::class, 'saveBranding'])->name('branding.save');

        Route::post('/{theme}/assets', [ThemeController::class, 'uploadAsset'])->name('assets.upload');
        Route::post('/assets/{asset}/archive', [ThemeController::class, 'archiveAsset'])->name('assets.archive');
    });

    // IMP-007 — Campaign/Program/Fund admin UI (docs/implementation/
    // IMP-007-campaign-program-fund.md section 21). {program}/{campaign}/
    // {fund} are bound by ULID, never the internal BIGINT id.
    Route::prefix('admin/campaign/programs')->name('campaign.programs.')->group(function () {
        Route::get('/', [AdminProgramController::class, 'index'])->name('index');
        Route::get('/create', [AdminProgramController::class, 'create'])->name('create');
        Route::post('/', [AdminProgramController::class, 'store'])->name('store');
        Route::get('/{program}', [AdminProgramController::class, 'show'])->name('show');
        Route::patch('/{program}', [AdminProgramController::class, 'update'])->name('update');
        Route::post('/{program}/publish', [AdminProgramController::class, 'publish'])->name('publish');
        Route::post('/{program}/unpublish', [AdminProgramController::class, 'unpublish'])->name('unpublish');
        Route::post('/{program}/archive', [AdminProgramController::class, 'archive'])->name('archive');
        Route::post('/{program}/media', [AdminProgramController::class, 'uploadAsset'])->name('media.upload');
        Route::post('/media/{asset}/archive', [AdminProgramController::class, 'archiveAsset'])->name('media.archive');
    });

    Route::prefix('admin/campaign/funds')->name('campaign.funds.')->group(function () {
        Route::get('/', [FundController::class, 'index'])->name('index');
        Route::get('/create', [FundController::class, 'create'])->name('create');
        Route::post('/', [FundController::class, 'store'])->name('store');
        Route::get('/{fund}', [FundController::class, 'edit'])->name('edit');
        Route::patch('/{fund}', [FundController::class, 'update'])->name('update');
        Route::post('/{fund}/archive', [FundController::class, 'archive'])->name('archive');
    });

    Route::prefix('admin/campaign/campaigns')->name('campaign.campaigns.')->group(function () {
        Route::get('/', [AdminCampaignController::class, 'index'])->name('index');
        Route::get('/create', [AdminCampaignController::class, 'create'])->name('create');
        Route::post('/', [AdminCampaignController::class, 'store'])->name('store');
        Route::get('/{campaign}', [AdminCampaignController::class, 'show'])->name('show');
        Route::patch('/{campaign}', [AdminCampaignController::class, 'update'])->name('update');
        Route::post('/{campaign}/submit', [AdminCampaignController::class, 'submit'])->name('submit');
        Route::post('/{campaign}/approve', [AdminCampaignController::class, 'approve'])->name('approve');
        Route::post('/{campaign}/reject', [AdminCampaignController::class, 'reject'])->name('reject');
        Route::post('/{campaign}/publish', [AdminCampaignController::class, 'publish'])->name('publish');
        Route::post('/{campaign}/close', [AdminCampaignController::class, 'close'])->name('close');
        Route::post('/{campaign}/media', [AdminCampaignController::class, 'uploadAsset'])->name('media.upload');
        Route::post('/media/{asset}/archive', [AdminCampaignController::class, 'archiveAsset'])->name('media.archive');
    });
});

// IMP-007 — public Program/Campaign routes (docs/implementation/
// IMP-007-campaign-program-fund.md section 21). Bound by `slug`, not the
// admin ULID key — registered before the IMP-006 catch-all below.
Route::get('/programs/{program:slug}', [PublicProgramController::class, 'show'])->name('public.programs.show');
Route::get('/campaigns', [PublicCampaignController::class, 'index'])->name('public.campaigns.index');
Route::get('/campaigns/{campaign:slug}', [PublicCampaignController::class, 'show'])->name('public.campaigns.show');

// IMP-006 — the public content catch-all (docs/implementation/
// IMP-006-theme-engine.md section 13), registered LAST so every
// already-registered system route above always matches first — no
// maintained exclusion list.
Route::get('/{any}', [PublicContentController::class, 'show'])
    ->where('any', '.*')
    ->name('public.show');
