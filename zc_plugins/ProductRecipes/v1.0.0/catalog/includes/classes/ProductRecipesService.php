<?php
/**
 * Product Recipes -- every query the plugin runs, in one plain class.
 *
 * No namespace and no reliance on Zen Cart's class loaders (they differ between 1.5.8, 2.0 and
 * 2.1+): each entry point requires this file through __DIR__ behind
 * class_exists('ProductRecipesService', false), which also keeps a Plugin Manager upgrade from
 * declaring the class twice.
 *
 * The public methods are a stable API from v1.0.0; Product Recipes Pro calls them.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
class ProductRecipesService
{
    /**
     * The visitor's (or admin's) language id.
     */
    public static function languageId(): int
    {
        return isset($_SESSION['languages_id']) ? (int)$_SESSION['languages_id'] : 1;
    }

    /**
     * A configuration value, with a default when the key isn't there (plugin not installed yet,
     * or a key added in a later version before its upgrade ran).
     *
     * @param mixed $default
     * @return mixed
     */
    public static function config(string $key, $default)
    {
        return defined($key) ? constant($key) : $default;
    }

    public static function isOn(string $key, bool $default = true): bool
    {
        $value = self::config($key, $default ? 'true' : 'false');
        return strtolower((string)$value) === 'true';
    }

    public static function enabled(): bool
    {
        return self::isOn('PRODUCT_RECIPES_STATUS', false);
    }

    /* ------------------------------------------------------------------ *
     * Recipes
     * ------------------------------------------------------------------ */

    /**
     * The pieces of the index query, so the page can hand them to
     * NOTIFY_PRODUCT_RECIPES_LISTING_QUERY before they're joined.
     *
     * @return array{select: string, from: string, where: string, orderBy: string}
     */
    public static function getListingParts(int $languageId, int $methodId = 0): array
    {
        $from = TABLE_PRODUCT_RECIPES . " r
                 INNER JOIN " . TABLE_PRODUCT_RECIPES_DESCRIPTION . " rd
                    ON rd.recipes_id = r.recipes_id AND rd.language_id = " . (int)$languageId;
        if ($methodId > 0) {
            $from .= "
                 INNER JOIN " . TABLE_PRODUCT_RECIPES_TO_METHODS . " rm
                    ON rm.recipes_id = r.recipes_id AND rm.methods_id = " . (int)$methodId;
        }
        return [
            'select' => "SELECT r.recipes_id, r.recipes_image, r.servings, r.prep_minutes, r.cook_minutes,
                                rd.recipes_name, rd.recipes_intro",
            'from' => " FROM " . $from,
            'where' => " WHERE r.recipes_status = 1 AND rd.recipes_name != ''",
            'orderBy' => " ORDER BY r.sort_order, rd.recipes_name",
        ];
    }

    /**
     * @param array{select: string, from: string, where: string, orderBy: string} $parts
     */
    public static function joinListingParts(array $parts): string
    {
        return $parts['select'] . $parts['from'] . $parts['where'] . $parts['orderBy'];
    }

    /**
     * One recipe with its ingredients and methods, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function getRecipe(int $recipesId, int $languageId, bool $activeOnly = true): ?array
    {
        global $db;
        if ($recipesId <= 0) {
            return null;
        }
        $result = $db->Execute(
            "SELECT r.*, rd.recipes_name, rd.recipes_intro, rd.recipes_instructions, rd.recipes_notes
               FROM " . TABLE_PRODUCT_RECIPES . " r
                    LEFT JOIN " . TABLE_PRODUCT_RECIPES_DESCRIPTION . " rd
                      ON rd.recipes_id = r.recipes_id AND rd.language_id = " . (int)$languageId . "
              WHERE r.recipes_id = " . (int)$recipesId
              . ($activeOnly ? " AND r.recipes_status = 1" : "") . "
              LIMIT 1"
        );
        if ($result->EOF) {
            return null;
        }
        $recipe = $result->fields;
        foreach (['recipes_name', 'recipes_intro', 'recipes_instructions', 'recipes_notes'] as $field) {
            $recipe[$field] = (string)($recipe[$field] ?? '');
        }
        if ($activeOnly && $recipe['recipes_name'] === '') {
            return null;
        }
        $recipe['ingredients'] = self::getIngredients($recipesId, $languageId);
        $methods = self::getMethodsForRecipes([$recipesId], $languageId, $activeOnly);
        $recipe['methods'] = $methods[$recipesId] ?? [];
        return $recipe;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getIngredients(int $recipesId, int $languageId): array
    {
        global $db;
        $rows = [];
        $result = $db->Execute(
            "SELECT i.ingredients_id, i.recipes_id, i.sort_order, i.products_id, i.amount, i.cart_qty, i.optional,
                    id.ingredient_text, id.unit
               FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " i
                    LEFT JOIN " . TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION . " id
                      ON id.ingredients_id = i.ingredients_id AND id.language_id = " . (int)$languageId . "
              WHERE i.recipes_id = " . (int)$recipesId . "
              ORDER BY i.sort_order, i.ingredients_id"
        );
        while (!$result->EOF) {
            $row = $result->fields;
            $row['ingredients_id'] = (int)$row['ingredients_id'];
            $row['products_id'] = (int)$row['products_id'];
            $row['optional'] = (int)$row['optional'];
            $row['ingredient_text'] = (string)($row['ingredient_text'] ?? '');
            $row['unit'] = (string)($row['unit'] ?? '');
            $rows[] = $row;
            $result->MoveNext();
        }
        return $rows;
    }

    /**
     * Newest active recipes, for the sidebox.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getLatest(int $limit, int $languageId): array
    {
        global $db;
        $rows = [];
        if ($limit <= 0) {
            return $rows;
        }
        $result = $db->Execute(
            "SELECT r.recipes_id, rd.recipes_name
               FROM " . TABLE_PRODUCT_RECIPES . " r
                    INNER JOIN " . TABLE_PRODUCT_RECIPES_DESCRIPTION . " rd
                       ON rd.recipes_id = r.recipes_id AND rd.language_id = " . (int)$languageId . "
              WHERE r.recipes_status = 1 AND rd.recipes_name != ''
              ORDER BY r.date_added DESC, r.recipes_id DESC
              LIMIT " . (int)$limit
        );
        while (!$result->EOF) {
            $rows[] = $result->fields;
            $result->MoveNext();
        }
        return $rows;
    }

    /**
     * Active recipes that use a product (Pro's "Used in these recipes").
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRecipesForProduct(int $productsId, int $languageId): array
    {
        global $db;
        $rows = [];
        if ($productsId <= 0) {
            return $rows;
        }
        $result = $db->Execute(
            "SELECT DISTINCT r.recipes_id, rd.recipes_name, r.recipes_image, r.sort_order
               FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " i
                    INNER JOIN " . TABLE_PRODUCT_RECIPES . " r ON r.recipes_id = i.recipes_id AND r.recipes_status = 1
                    INNER JOIN " . TABLE_PRODUCT_RECIPES_DESCRIPTION . " rd
                       ON rd.recipes_id = r.recipes_id AND rd.language_id = " . (int)$languageId . "
              WHERE i.products_id = " . (int)$productsId . " AND rd.recipes_name != ''
              ORDER BY r.sort_order, rd.recipes_name"
        );
        while (!$result->EOF) {
            $rows[] = $result->fields;
            $result->MoveNext();
        }
        return $rows;
    }

    /* ------------------------------------------------------------------ *
     * Cooking methods
     * ------------------------------------------------------------------ */

    /**
     * Methods keyed by id: ['methods_id', 'methods_name', 'sort_order', 'methods_status'].
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getMethods(int $languageId, bool $activeOnly = true): array
    {
        global $db;
        $methods = [];
        $result = $db->Execute(
            "SELECT m.methods_id, m.sort_order, m.methods_status, md.methods_name
               FROM " . TABLE_PRODUCT_RECIPES_METHODS . " m
                    LEFT JOIN " . TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION . " md
                      ON md.methods_id = m.methods_id AND md.language_id = " . (int)$languageId
            . ($activeOnly ? " WHERE m.methods_status = 1" : "") . "
              ORDER BY m.sort_order, md.methods_name, m.methods_id"
        );
        while (!$result->EOF) {
            $id = (int)$result->fields['methods_id'];
            $methods[$id] = [
                'methods_id' => $id,
                'methods_name' => (string)($result->fields['methods_name'] ?? ''),
                'sort_order' => (int)$result->fields['sort_order'],
                'methods_status' => (int)$result->fields['methods_status'],
            ];
            $result->MoveNext();
        }
        return $methods;
    }

    /**
     * Methods per recipe: [recipes_id => [['methods_id' => n, 'methods_name' => s], ...]].
     *
     * @param int[] $recipeIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function getMethodsForRecipes(array $recipeIds, int $languageId, bool $activeOnly = true): array
    {
        global $db;
        $ids = array_values(array_filter(array_map('intval', $recipeIds), static function ($id) {
            return $id > 0;
        }));
        $map = [];
        if ($ids === []) {
            return $map;
        }
        $result = $db->Execute(
            "SELECT rm.recipes_id, m.methods_id, md.methods_name
               FROM " . TABLE_PRODUCT_RECIPES_TO_METHODS . " rm
                    INNER JOIN " . TABLE_PRODUCT_RECIPES_METHODS . " m ON m.methods_id = rm.methods_id
                    LEFT JOIN " . TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION . " md
                      ON md.methods_id = m.methods_id AND md.language_id = " . (int)$languageId . "
              WHERE rm.recipes_id IN (" . implode(',', $ids) . ")"
              . ($activeOnly ? " AND m.methods_status = 1" : "") . "
              ORDER BY m.sort_order, md.methods_name"
        );
        while (!$result->EOF) {
            $map[(int)$result->fields['recipes_id']][] = [
                'methods_id' => (int)$result->fields['methods_id'],
                'methods_name' => (string)($result->fields['methods_name'] ?? ''),
            ];
            $result->MoveNext();
        }
        return $map;
    }

    /* ------------------------------------------------------------------ *
     * Products
     * ------------------------------------------------------------------ */

    /**
     * Basic facts for a set of products, keyed by id. Products that no longer exist are absent.
     *
     * @param int[] $productIds
     * @return array<int, array<string, mixed>>
     */
    public static function getProductsInfo(array $productIds, int $languageId): array
    {
        global $db;
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static function ($id) {
            return $id > 0;
        })));
        $info = [];
        if ($ids === []) {
            return $info;
        }
        $result = $db->Execute(
            "SELECT p.products_id, p.products_status, p.products_quantity, p.product_is_call, p.products_model,
                    pd.products_name
               FROM " . TABLE_PRODUCTS . " p
                    LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
                      ON pd.products_id = p.products_id AND pd.language_id = " . (int)$languageId . "
              WHERE p.products_id IN (" . implode(',', $ids) . ")"
        );
        while (!$result->EOF) {
            $id = (int)$result->fields['products_id'];
            $info[$id] = [
                'products_id' => $id,
                'active' => (int)$result->fields['products_status'] === 1,
                'quantity' => (float)$result->fields['products_quantity'],
                'is_call' => (int)$result->fields['product_is_call'] === 1,
                'model' => (string)$result->fields['products_model'],
                'name' => (string)($result->fields['products_name'] ?? ''),
            ];
            $result->MoveNext();
        }
        return $info;
    }

    /**
     * A product ID or model, as typed in the admin, to a products_id (0 when nothing matches).
     */
    public static function resolveProductRef(string $ref): int
    {
        global $db;
        $ref = trim($ref);
        if ($ref === '') {
            return 0;
        }
        if (preg_match('/^\d+$/', $ref) === 1) {
            $check = $db->Execute("SELECT products_id FROM " . TABLE_PRODUCTS . " WHERE products_id = " . (int)$ref . " LIMIT 1");
            if (!$check->EOF) {
                return (int)$check->fields['products_id'];
            }
        }
        $check = $db->Execute(
            "SELECT products_id FROM " . TABLE_PRODUCTS . "
              WHERE products_model = '" . zen_db_input($ref) . "'
              ORDER BY products_status DESC, products_id
              LIMIT 1"
        );
        return $check->EOF ? 0 : (int)$check->fields['products_id'];
    }

    /**
     * Admin product search by name or model.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function findProducts(string $term, int $languageId, int $limit = 25): array
    {
        global $db;
        $rows = [];
        $term = trim($term);
        if ($term === '') {
            return $rows;
        }
        $like = zen_db_input(addcslashes($term, '%_\\'));
        $exactId = (preg_match('/^\d+$/', $term) === 1) ? " OR p.products_id = " . (int)$term : '';
        $result = $db->Execute(
            "SELECT p.products_id, p.products_model, p.products_price, p.products_status, pd.products_name
               FROM " . TABLE_PRODUCTS . " p
                    LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
                      ON pd.products_id = p.products_id AND pd.language_id = " . (int)$languageId . "
              WHERE pd.products_name LIKE '%" . $like . "%'
                 OR p.products_model LIKE '%" . $like . "%'" . $exactId . "
              ORDER BY p.products_status DESC, pd.products_name
              LIMIT " . max(1, $limit)
        );
        while (!$result->EOF) {
            $rows[] = $result->fields;
            $result->MoveNext();
        }
        return $rows;
    }

    /* ------------------------------------------------------------------ *
     * Text helpers
     * ------------------------------------------------------------------ */

    /**
     * Kitchen fractions: 1.5 -> "1 1/2", 0.333 -> "1/3", 2 -> "2", 1.2 -> "1.2", null/0 -> "".
     *
     * @param mixed $amount
     */
    public static function formatAmount($amount): string
    {
        if ($amount === null || $amount === '' || !is_numeric($amount) || (float)$amount <= 0.0) {
            return '';
        }
        $value = (float)$amount;
        $whole = (int)floor($value + 0.0005);
        $fraction = $value - $whole;
        if ($fraction < 0.005) {
            return (string)$whole;
        }
        $fractions = [
            '1/8' => 0.125, '1/4' => 0.25, '1/3' => 0.3333, '3/8' => 0.375, '1/2' => 0.5,
            '5/8' => 0.625, '2/3' => 0.6667, '3/4' => 0.75, '7/8' => 0.875,
        ];
        foreach ($fractions as $text => $decimal) {
            if (abs($fraction - $decimal) < 0.01) {
                return ($whole > 0 ? $whole . ' ' : '') . $text;
            }
        }
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    /**
     * The reverse, for the admin: "1 1/2", "1/2", "1.5", "1,5", "½" -> float; blank -> null;
     * anything else -> false.
     *
     * @return float|false|null
     */
    public static function parseAmount(string $text)
    {
        $text = trim(strtr($text, [
            "\u{00BD}" => ' 1/2', "\u{00BC}" => ' 1/4', "\u{00BE}" => ' 3/4',
            "\u{2153}" => ' 1/3', "\u{2154}" => ' 2/3', "\u{215B}" => ' 1/8',
        ]));
        $text = (string)preg_replace('/\s+/', ' ', $text);
        if ($text === '') {
            return null;
        }
        if (preg_match('/^(\d+) (\d+)\/(\d+)$/', $text, $m) === 1 && (int)$m[3] > 0) {
            return (int)$m[1] + (int)$m[2] / (int)$m[3];
        }
        if (preg_match('/^(\d+)\/(\d+)$/', $text, $m) === 1 && (int)$m[2] > 0) {
            return (int)$m[1] / (int)$m[2];
        }
        if (strpos($text, '.') === false) {
            $text = str_replace(',', '.', $text);
        }
        if (is_numeric($text) && (float)$text >= 0 && (float)$text < 10000000) {
            return (float)$text;
        }
        return false;
    }

    /**
     * Plain text from HTML, cut at a word boundary. UTF-8 safe without mbstring (the PHP builds
     * on some hosts and on the test rig don't have it).
     */
    public static function plainText(string $html, int $length = 0): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));
        if ($length <= 0 || preg_match('/^.{' . ($length + 1) . '}/us', $text) !== 1) {
            return $text;
        }
        if (preg_match('/^(.{1,' . $length . '})(?:\s|$)/us', $text, $m) === 1) {
            return rtrim($m[1], " ,.;:") . '...';
        }
        preg_match('/^.{' . $length . '}/us', $text, $m);
        return $m[0] . '...';
    }

    /**
     * Define any key missing from a plugin language array file, using the English file.
     *
     * Zen Cart 1.5.8 and 2.0.x don't fall back to English for plugin language files, so on a store
     * whose language has no translation every constant would be undefined (a PHP 8 fatal). Called
     * after the store's own language has loaded, so a translation always wins.
     */
    public static function defineFallbacks(string $englishFile): void
    {
        if (!is_file($englishFile)) {
            return;
        }
        $define = null;
        $defines = include $englishFile;
        if (!is_array($defines)) {
            $defines = is_array($define) ? $define : [];
        }
        foreach ($defines as $key => $value) {
            if (is_string($key) && !defined($key)) {
                define($key, $value);
            }
        }
    }
}
