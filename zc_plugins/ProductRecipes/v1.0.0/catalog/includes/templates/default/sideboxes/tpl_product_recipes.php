<?php
/**
 * Product Recipes -- Recipes sidebox content. A template can override this file by copying it
 * to includes/templates/<template>/sideboxes/.
 *
 * In: $productRecipesBoxLatest, $productRecipesBoxMethods. Out: $content.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
// Built like the Information box on ZCA Bootstrap and BS5 (links that are list-group rows), so it
// matches the template's own boxes there. display:block keeps one link per line on templates
// without Bootstrap. The plugin's stylesheet is loaded on the Recipes page only, so nothing here
// depends on it.
$productRecipesBoxLink = static function ($href, $text, $class) {
    return '<a class="list-group-item list-group-item-action ' . $class . '" style="display:block" href="' . $href . '">' . $text . '</a>' . "\n";
};
$content = '<div id="' . str_replace('_', '-', $box_id . 'Content') . '" class="sideBoxContent productRecipesBox list-group list-group-flush">';

foreach ($productRecipesBoxLatest as $productRecipesBoxItem) {
    $content .= $productRecipesBoxLink(
        zen_href_link(FILENAME_RECIPES, 'recipes_id=' . (int)$productRecipesBoxItem['recipes_id']),
        zen_output_string_protected($productRecipesBoxItem['recipes_name']),
        'productRecipesBoxLatest'
    );
}

$productRecipesBoxNamed = array_filter($productRecipesBoxMethods, static function ($method) {
    return $method['methods_name'] !== '';
});
if ($productRecipesBoxNamed !== []) {
    $content .= '<div class="list-group-item productRecipesBoxHeading"><strong>' . BOX_PRODUCT_RECIPES_METHODS . '</strong></div>' . "\n";
    foreach ($productRecipesBoxNamed as $productRecipesBoxMethod) {
        $content .= $productRecipesBoxLink(
            zen_href_link(FILENAME_RECIPES, 'method=' . (int)$productRecipesBoxMethod['methods_id']),
            zen_output_string_protected($productRecipesBoxMethod['methods_name']),
            'productRecipesBoxMethod'
        );
    }
}

$content .= $productRecipesBoxLink(zen_href_link(FILENAME_RECIPES), BOX_PRODUCT_RECIPES_ALL, 'productRecipesBoxAll');
$content .= '</div>';
