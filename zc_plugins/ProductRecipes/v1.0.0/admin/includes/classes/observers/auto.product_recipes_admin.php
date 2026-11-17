<?php
/**
 * Product Recipes -- admin observer.
 *
 * Auto-discovered by init_observers.php (class name = zcObserver + camelized file name); it has
 * no auto_loader entry, because registering it there too would declare it twice.
 *
 * - NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT (every release 1.5.8 -> 3.0.0, product id in the second
 *   parameter): a deleted product's ingredient lines are unlinked and stay as text.
 * - NOTIFY_ADMIN_LANGUAGE_INSERT / _DELETE (every release, language id in the first parameter):
 *   a new language gets a copy of the recipe, ingredient and cooking-method text in the admin's
 *   current language, the way core copies product text; a deleted language's text goes with it.
 * - NOTIFIER_PLUGIN_HELP_PAGE_URL_LOOKUP: the Help button on Extras > Recipes opens the
 *   plugin's GitHub page.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
if (!class_exists('ProductRecipesService', false)) {
    require_once __DIR__ . '/../../../../catalog/includes/classes/ProductRecipesService.php';
}

class zcObserverProductRecipesAdmin extends base
{
    public function __construct()
    {
        // The menu labels are used on every admin page. 1.5.8 and 2.0.x don't fall back to
        // English for plugin language files, and an undefined constant there is a PHP 8 fatal.
        // Observers load after the admin languages, so a translation still wins.
        ProductRecipesService::defineFallbacks(__DIR__ . '/../../languages/english/extra_definitions/lang.product_recipes.php');

        $this->attach($this, [
            'NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT',
            'NOTIFY_ADMIN_LANGUAGE_INSERT',
            'NOTIFY_ADMIN_LANGUAGE_DELETE',
            'NOTIFIER_PLUGIN_HELP_PAGE_URL_LOOKUP',
        ]);
    }

    /**
     * The per-language tables and the columns each copies to a new language.
     *
     * @return array<string, array{0: string, 1: string}> table => [id column, text columns]
     */
    protected function languageTables(): array
    {
        return [
            TABLE_PRODUCT_RECIPES_DESCRIPTION => ['recipes_id', 'recipes_name, recipes_intro, recipes_instructions, recipes_notes'],
            TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION => ['ingredients_id', 'ingredient_text, unit'],
            TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION => ['methods_id', 'methods_name'],
        ];
    }

    /**
     * @param mixed $class
     * @param mixed $p1
     * @param mixed $p2
     * @param mixed $p3
     */
    public function update(&$class, $eventID, $p1, &$p2 = null, &$p3 = null)
    {
        global $db;

        if ($eventID === 'NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT') {
            if (defined('TABLE_PRODUCT_RECIPES_INGREDIENTS') && (int)$p2 > 0) {
                // A line whose text was left blank showed the product's name. Core fires this
                // before it deletes the product's descriptions, so keep that name as the text.
                $db->Execute(
                    "UPDATE " . TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION . " id
                            INNER JOIN " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " i ON i.ingredients_id = id.ingredients_id
                            INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
                               ON pd.products_id = i.products_id AND pd.language_id = id.language_id
                        SET id.ingredient_text = LEFT(pd.products_name, 255)
                      WHERE i.products_id = " . (int)$p2 . " AND id.ingredient_text = ''"
                );
                $db->Execute(
                    "UPDATE " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " SET products_id = 0 WHERE products_id = " . (int)$p2
                );
            }
            return;
        }

        if ($eventID === 'NOTIFY_ADMIN_LANGUAGE_INSERT' || $eventID === 'NOTIFY_ADMIN_LANGUAGE_DELETE') {
            $languageId = (int)$p1;
            if ($languageId <= 0 || !defined('TABLE_PRODUCT_RECIPES_DESCRIPTION')) {
                return;
            }
            // The admin's current language is the copy's source, as in core's languages.php.
            $sourceId = isset($_SESSION['languages_id']) ? (int)$_SESSION['languages_id'] : 0;
            foreach ($this->languageTables() as $table => [$idColumn, $textColumns]) {
                if ($eventID === 'NOTIFY_ADMIN_LANGUAGE_DELETE') {
                    $db->Execute("DELETE FROM " . $table . " WHERE language_id = " . $languageId);
                } elseif ($sourceId > 0 && $sourceId !== $languageId) {
                    $db->Execute(
                        "INSERT IGNORE INTO " . $table . " (" . $idColumn . ", language_id, " . $textColumns . ")
                         SELECT " . $idColumn . ", " . $languageId . ", " . $textColumns . "
                           FROM " . $table . " WHERE language_id = " . $sourceId
                    );
                }
            }
            return;
        }

        if ($eventID === 'NOTIFIER_PLUGIN_HELP_PAGE_URL_LOOKUP') {
            // $p1 is the current page without ".php"; $p2 is the help URL, by reference.
            if ($p1 === 'product_recipes' || $p1 === 'product_recipes_readme') {
                $p2 = 'https://github.com/dbltoe/Product_Recipes';
            }
        }
    }
}
