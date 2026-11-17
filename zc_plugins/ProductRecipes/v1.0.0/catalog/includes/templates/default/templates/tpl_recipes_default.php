<?php
/**
 * Product Recipes -- the Recipes page. Picks the index or the recipe template; either can be
 * overridden by copying it to includes/templates/<template>/templates/.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
?>
<div class="centerColumn productRecipesPage" id="productRecipes">
<?php
if ($productRecipesMode === 'recipe') {
    require $template->get_template_dir('tpl_modules_product_recipes_recipe.php', DIR_WS_TEMPLATE, $current_page_base, 'templates') . '/tpl_modules_product_recipes_recipe.php';
} elseif ($productRecipesMode === 'notfound') {
    ?>
    <h1 id="productRecipesHeading"><?php echo HEADING_TITLE_RECIPES; ?></h1>
    <p class="productRecipesNotFound"><?php echo TEXT_RECIPES_NOT_FOUND; ?></p>
    <p><a href="<?php echo zen_href_link(FILENAME_RECIPES); ?>"><?php echo TEXT_RECIPES_BACK; ?></a></p>
    <?php
} else {
    require $template->get_template_dir('tpl_modules_product_recipes_listing.php', DIR_WS_TEMPLATE, $current_page_base, 'templates') . '/tpl_modules_product_recipes_listing.php';
}
?>
</div>
