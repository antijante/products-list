<?php

namespace Antijante\ProductsList;

/**
 * The quick edit panel's arrangement, declared by an extension through
 * `antijante_products_list/layout` (docs/contracts.md §6, Layout).
 *
 * - **Scopes** are the panel's main level, a rail on its left (a select on a
 *   narrow panel): for a translated shop, "All markets" and then one per
 *   market. Each holds tabs. A scope with several tabs shows them as section
 *   tabs; with one, its tab is one scroll. A tab no scope names goes to the
 *   first scope.
 * - **Tabs** may take fields from where they would otherwise sit (`fields`:
 *   field ids) and may set their own sections (`sections`: a label and field
 *   ids each, in order; a field of the tab no section names follows in its
 *   usual section), and may leave fields out (`hide`: a market that sells in
 *   the shop's currency has no price of its own).
 *
 * Without a declared layout the panel is the tab strip it always was.
 *
 * @phpstan-type Section array{id: string, label: string, description: string, fields: array<int, string>}
 * @phpstan-type Tab array{id: string, label: ?string, order: ?int, fields: array<int, string>, hide: array<int, string>, sections: array<int, Section>}
 * @phpstan-type Scope array{id: string, label: string, detail: string, badge: string, heading: string, intro: string, link: ?array{url: string, label: string}, tabs: array<int, string>, missing: bool, missingFallback: string}
 * @phpstan-type LayoutDef array{scopes: array<int, Scope>, tabs: array<int, Tab>}
 */
final class Layout
{
    public const FILTER = 'antijante_products_list/layout';

    private const ID = '/^[a-z0-9][a-z0-9_:.\-]{0,99}$/';

    /** @var LayoutDef|null */
    private static ?array $layout = null;

    public static function reset(): void
    {
        self::$layout = null;
    }

    /**
     * @return LayoutDef
     */
    public static function get(): array
    {
        if (self::$layout !== null) {
            return self::$layout;
        }

        /**
         * Filters the quick edit panel's scopes and tabs.
         *
         * @param  array<string, mixed>  $layout  `scopes` and `tabs`, as in the class comment
         */
        $layout = (array) apply_filters(self::FILTER, ['scopes' => [], 'tabs' => []]);

        return self::$layout = self::normalise($layout);
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return LayoutDef
     */
    public static function normalise(array $layout): array
    {
        $scopes = [];

        foreach ((array) ($layout['scopes'] ?? []) as $scope) {
            if (! is_array($scope) || ! self::id($scope['id'] ?? null)) {
                continue;
            }

            $link = $scope['link'] ?? null;

            $scopes[(string) $scope['id']] = [
                'id' => (string) $scope['id'],
                'label' => self::text($scope['label'] ?? $scope['id']),
                'detail' => self::text($scope['detail'] ?? ''),
                'badge' => self::text($scope['badge'] ?? ''),
                'heading' => self::text($scope['heading'] ?? ''),
                'intro' => self::text($scope['intro'] ?? ''),
                'link' => is_array($link) && isset($link['url'], $link['label']) && is_string($link['url']) && $link['url'] !== ''
                    ? ['url' => function_exists('esc_url_raw') ? esc_url_raw($link['url']) : (string) filter_var($link['url'], FILTER_SANITIZE_URL), 'label' => self::text($link['label'])]
                    : null,
                'tabs' => self::ids($scope['tabs'] ?? []),
                'missing' => ! empty($scope['missing']),
                'missingFallback' => self::text($scope['missingFallback'] ?? ''),
            ];
        }

        $tabs = [];

        foreach ((array) ($layout['tabs'] ?? []) as $tab) {
            if (! is_array($tab) || ! self::id($tab['id'] ?? null)) {
                continue;
            }

            $sections = [];

            foreach ((array) ($tab['sections'] ?? []) as $section) {
                if (! is_array($section) || ! self::id($section['id'] ?? null)) {
                    continue;
                }

                $sections[] = [
                    'id' => (string) $section['id'],
                    'label' => self::text($section['label'] ?? $section['id']),
                    'description' => self::text($section['description'] ?? ''),
                    'fields' => self::ids($section['fields'] ?? []),
                ];
            }

            $tabs[(string) $tab['id']] = [
                'id' => (string) $tab['id'],
                'label' => isset($tab['label']) ? self::text($tab['label']) : null,
                'order' => isset($tab['order']) && is_numeric($tab['order']) ? (int) $tab['order'] : null,
                'fields' => self::ids($tab['fields'] ?? []),
                'hide' => self::ids($tab['hide'] ?? []),
                'sections' => $sections,
            ];
        }

        return ['scopes' => array_values($scopes), 'tabs' => array_values($tabs)];
    }

    private static function id(mixed $id): bool
    {
        return is_string($id) && preg_match(self::ID, $id) === 1;
    }

    /**
     * @return array<int, string>
     */
    private static function ids(mixed $ids): array
    {
        return array_values(array_unique(array_filter(array_map('strval', array_filter((array) $ids, 'is_scalar')), [self::class, 'id'])));
    }

    private static function text(mixed $text): string
    {
        return is_scalar($text) ? trim(strip_tags((string) $text)) : '';
    }
}
