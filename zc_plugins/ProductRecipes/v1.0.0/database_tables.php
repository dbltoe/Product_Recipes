<?php
/**
 * Product Recipes -- table name constants.
 *
 * Auto-loaded from the plugin root on Zen Cart 2.2.0 and later; the admin and catalog
 * extra_datafiles require this same file for 1.5.8 through 2.1. Every define is guarded, so
 * loading it twice (or from two versions during an upgrade) is harmless.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('DB_PREFIX')) {
    return;
}
$prTables = [
    'TABLE_PRODUCT_RECIPES' => 'product_recipes',
    'TABLE_PRODUCT_RECIPES_DESCRIPTION' => 'product_recipes_description',
    'TABLE_PRODUCT_RECIPES_INGREDIENTS' => 'product_recipes_ingredients',
    'TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION' => 'product_recipes_ingredients_description',
    'TABLE_PRODUCT_RECIPES_METHODS' => 'product_recipes_methods',
    'TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION' => 'product_recipes_methods_description',
    'TABLE_PRODUCT_RECIPES_TO_METHODS' => 'product_recipes_to_methods',
];
foreach ($prTables as $prConstant => $prTable) {
    if (!defined($prConstant)) {
        define($prConstant, DB_PREFIX . $prTable);
    }
}
unset($prTables, $prConstant, $prTable);
