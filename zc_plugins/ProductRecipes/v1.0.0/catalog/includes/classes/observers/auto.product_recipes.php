<?php
/**
 * Product Recipes -- storefront observer.
 *
 * Auto-discovered by init_observers.php (class name = zcObserver + camelized file name), with no
 * auto_loader entry. Observers load after the language files, so the English fallback for this
 * plugin's every-page text is defined here.
 *
 * - NOTIFY_HTML_HEAD_END: link product_recipes.css on the Recipes page only.
 * - NOTIFY_INFORMATION_SIDEBOX_ADDITIONS: a Recipes link in the Information sidebox (Zen Cart
 *   2.0.0 and later; 1.5.8 has no such notifier).
 * - NOTIFY_MODULE_META_TAGS_UNSPECIFIEDPAGE: the page's title and description.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
if (!class_exists('ProductRecipesService', false)) {
    require_once __DIR__ . '/../ProductRecipesService.php';
}

class zcObserverProductRecipes extends base
{
    public function __construct()
    {
        ProductRecipesService::defineFallbacks(__DIR__ . '/../../languages/english/extra_definitions/lang.product_recipes.php');

        if (!ProductRecipesService::enabled()) {
            return;
        }
        $this->attach($this, [
            'NOTIFY_HTML_HEAD_END',
            'NOTIFY_INFORMATION_SIDEBOX_ADDITIONS',
            'NOTIFY_MODULE_META_TAGS_UNSPECIFIEDPAGE',
        ]);
    }

    /**
     * @param mixed $class
     * @param mixed $p1
     * @param mixed $p2
     * @param mixed $p3
     * @param mixed $p4
     * @param mixed $p5
     * @param mixed $p6
     */
    public function update(&$class, $eventID, $p1, &$p2 = null, &$p3 = null, &$p4 = null, &$p5 = null, &$p6 = null)
    {
        switch ($eventID) {
            case 'NOTIFY_HTML_HEAD_END':
                // $p1 is $current_page_base.
                if ($p1 === FILENAME_RECIPES) {
                    echo '<link rel="stylesheet" href="' . zen_output_string_protected($this->stylesheetUrl()) . '">' . "\n";
                }
                break;

            case 'NOTIFY_INFORMATION_SIDEBOX_ADDITIONS':
                global $current_page_base;
                if (ProductRecipesService::isOn('PRODUCT_RECIPES_INFO_SIDEBOX_LINK') && $current_page_base !== FILENAME_RECIPES && is_array($p2)) {
                    // list-group classes match the other links on Bootstrap templates and are
                    // ignored elsewhere.
                    $p2[] = '<a class="list-group-item list-group-item-action" href="' . zen_href_link(FILENAME_RECIPES) . '">'
                        . BOX_INFORMATION_PRODUCT_RECIPES . '</a>';
                }
                break;

            case 'NOTIFY_MODULE_META_TAGS_UNSPECIFIEDPAGE':
                // $p1 page, $p3 override flag, $p4 title, $p5 description, $p6 keywords (by reference).
                global $productRecipesMeta;
                if ($p1 === FILENAME_RECIPES && is_array($productRecipesMeta)) {
                    $site = defined('TITLE') ? TITLE : '';
                    $separator = defined('PRIMARY_SECTION') ? PRIMARY_SECTION : ' : ';
                    $title = ProductRecipesService::plainText((string)$productRecipesMeta['title']);
                    $description = (string)$productRecipesMeta['description'];
                    // Without the override flag core rebuilds the tags from NAVBAR_TITLE.
                    $p3 = true;
                    $p4 = zen_output_string_protected($title . ($site !== '' ? $separator . $site : ''));
                    $p5 = zen_output_string_protected($description !== '' ? $description : $title);
                    $p6 = zen_output_string_protected($title . (defined('KEYWORDS') ? ', ' . KEYWORDS : ''));
                }
                break;
        }
    }

    /**
     * The stylesheet's URL, built from where this file really is, so nothing needs editing when
     * the version folder changes.
     */
    protected function stylesheetUrl(): string
    {
        $version = basename(dirname(__DIR__, 4));
        $key = basename(dirname(__DIR__, 5));
        return DIR_WS_CATALOG . 'zc_plugins/' . rawurlencode($key) . '/' . rawurlencode($version)
            . '/catalog/includes/templates/default/css/product_recipes.css';
    }
}
