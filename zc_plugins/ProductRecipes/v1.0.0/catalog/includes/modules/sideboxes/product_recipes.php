<?php
/**
 * Product Recipes -- the Recipes sidebox (turn it on in Tools > Layout Boxes Controller).
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
if (!class_exists('ProductRecipesService', false)) {
    require_once __DIR__ . '/../../classes/ProductRecipesService.php';
}

if (ProductRecipesService::enabled() && defined('TABLE_PRODUCT_RECIPES')) {
    $productRecipesBoxLanguage = ProductRecipesService::languageId();
    $productRecipesBoxLatest = ProductRecipesService::getLatest(
        max(0, (int)ProductRecipesService::config('PRODUCT_RECIPES_SIDEBOX_MAX', 3)),
        $productRecipesBoxLanguage
    );
    $productRecipesBoxMethods = ProductRecipesService::getMethods($productRecipesBoxLanguage);

    if ($productRecipesBoxLatest !== [] || $productRecipesBoxMethods !== []) {
        require $template->get_template_dir('tpl_product_recipes.php', DIR_WS_TEMPLATE, $current_page_base, 'sideboxes') . '/tpl_product_recipes.php';
        $title = BOX_HEADING_PRODUCT_RECIPES;
        $title_link = FILENAME_RECIPES;
        require $template->get_template_dir($column_box_default, DIR_WS_TEMPLATE, $current_page_base, 'common') . '/' . $column_box_default;
    }
}
