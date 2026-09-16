<?php

namespace App\Services\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeNavigationItem;
use App\Models\Theme\ThemeNavigationMenu;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-006 — NavigationMenu/NavigationItem CRUD (docs/implementation/
 * IMP-006-theme-engine.md section 14, Q30). One level of nesting only — a
 * child item may never itself have children (enforced here, not by schema).
 */
class ThemeNavigationService
{
    public function __construct(
        private readonly NavigationDestinationValidator $destinationValidator,
        private readonly ThemeAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{code:string,name:string}  $payload
     */
    public function createMenu(Theme $theme, array $payload, Principal $actor): ThemeNavigationMenu
    {
        return DB::transaction(function () use ($theme, $payload, $actor) {
            if (ThemeNavigationMenu::where('theme_id', $theme->id)->where('code', $payload['code'])->exists()) {
                throw new ThemeValidationException('menu_code_taken', "Navigation menu code '{$payload['code']}' is already in use in this theme.");
            }

            $menu = new ThemeNavigationMenu;
            $menu->forceFill([
                'theme_id' => $theme->id,
                'code' => $payload['code'],
                'name' => $payload['name'],
            ]);
            $menu->save();

            $this->auditLogger->recordNavigationUpdated($menu->id, [
                'theme_id' => $theme->id,
                'fields_changed' => ['created'],
            ], $actor);

            return $menu;
        });
    }

    /**
     * @param  array{label:string,destination_type:string,destination_route?:?string,destination_content_kind?:?string,destination_content_ulid?:?string,destination_external_url?:?string,parent_id?:?int}  $payload
     */
    public function createItem(ThemeNavigationMenu $menu, array $payload, Principal $actor): ThemeNavigationItem
    {
        return DB::transaction(function () use ($menu, $payload, $actor) {
            $this->destinationValidator->assertValid($payload);

            $lockedMenu = ThemeNavigationMenu::query()->whereKey($menu->id)->lockForUpdate()->firstOrFail();
            $parentId = $payload['parent_id'] ?? null;

            if ($parentId !== null) {
                $parent = ThemeNavigationItem::query()->whereKey($parentId)->lockForUpdate()->firstOrFail();

                if ($parent->theme_navigation_menu_id !== $lockedMenu->id) {
                    throw new ThemeValidationException('cross_menu_parent', 'A child item\'s parent must belong to the same menu.');
                }

                if ($parent->parent_id !== null) {
                    throw new ThemeValidationException('nesting_too_deep', 'Navigation items support one level of nesting only (section 14).');
                }
            }

            $nextPosition = (int) (ThemeNavigationItem::where('theme_navigation_menu_id', $lockedMenu->id)
                ->where('parent_id', $parentId)
                ->max('position') ?? 0) + 1;

            $item = new ThemeNavigationItem;
            $item->forceFill([
                'theme_navigation_menu_id' => $lockedMenu->id,
                'parent_id' => $parentId,
                'label' => $payload['label'],
                'destination_type' => $payload['destination_type'],
                'destination_route' => $payload['destination_route'] ?? null,
                'destination_content_kind' => $payload['destination_content_kind'] ?? null,
                'destination_content_ulid' => $payload['destination_content_ulid'] ?? null,
                'destination_external_url' => $payload['destination_external_url'] ?? null,
                'position' => $nextPosition,
                'visible' => true,
            ]);
            $item->save();

            $this->auditLogger->recordNavigationUpdated($lockedMenu->id, [
                'theme_id' => $lockedMenu->theme_id,
                'fields_changed' => ['item_created'],
            ], $actor);

            return $item;
        });
    }

    public function deleteItem(ThemeNavigationItem $item, Principal $actor): void
    {
        DB::transaction(function () use ($item, $actor) {
            $locked = ThemeNavigationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $menuId = $locked->theme_navigation_menu_id;
            $themeId = $locked->menu()->value('theme_id');
            $locked->delete();

            $this->auditLogger->recordNavigationUpdated($menuId, [
                'theme_id' => $themeId,
                'fields_changed' => ['item_deleted'],
            ], $actor);
        });
    }
}
