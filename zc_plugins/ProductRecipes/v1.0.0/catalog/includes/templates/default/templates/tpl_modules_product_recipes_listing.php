<?php
/**
 * Product Recipes -- the recipe index.
 *
 * In: $productRecipesItems, $productRecipesItemMethods, $productRecipesFilters,
 *     $productRecipesSplit, $productRecipesMethodId.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
$productRecipesThumbWidth = max(0, (int)ProductRecipesService::config('PRODUCT_RECIPES_IMAGE_WIDTH', 220));
?>
<h1 id="productRecipesHeading"><?php echo HEADING_TITLE_RECIPES; ?></h1>
<?php if (TEXT_RECIPES_PAGE_INTRO !== '') { ?>
<div class="productRecipesIntro"><?php echo TEXT_RECIPES_PAGE_INTRO; ?></div>
<?php } ?>

<?php if ($productRecipesFilters !== []) { ?>
<nav class="productRecipesFilters" aria-label="<?php echo zen_output_string_protected(TEXT_RECIPES_FILTER_LABEL); ?>">
    <?php foreach ($productRecipesFilters as $productRecipesFilter) { ?>
    <a class="productRecipesFilter<?php echo !empty($productRecipesFilter['active']) ? ' active' : ''; ?>" href="<?php echo $productRecipesFilter['href']; ?>"<?php echo !empty($productRecipesFilter['active']) ? ' aria-current="page"' : ''; ?>><?php echo zen_output_string_protected($productRecipesFilter['text']); ?></a>
    <?php } ?>
</nav>
<?php } ?>

<?php
// For other plugins: draw above the cards (echo) and change the empty-list message.
$productRecipesNoneText = ($productRecipesMethodId > 0) ? TEXT_RECIPES_NONE_METHOD : TEXT_RECIPES_NONE;
$zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_TPL_BEFORE_LISTING', count($productRecipesItems), $productRecipesNoneText);
?>

<?php if ($productRecipesItems === []) { ?>
<p class="productRecipesNone"><?php echo $productRecipesNoneText; ?></p>
<?php } else { ?>
<div class="productRecipesGrid">
    <?php foreach ($productRecipesItems as $productRecipesItem) {
        $productRecipesItemId = (int)$productRecipesItem['recipes_id'];
        $productRecipesItemUrl = zen_href_link(FILENAME_RECIPES, 'recipes_id=' . $productRecipesItemId);
        $productRecipesItemName = zen_output_string_protected($productRecipesItem['recipes_name']);
        $productRecipesItemExtra = '';
        $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_TPL_LISTING_ITEM', $productRecipesItem, $productRecipesItemExtra);
        ?>
    <article class="productRecipesCard card">
        <?php if ($productRecipesItem['recipes_image'] !== '' && is_file(DIR_FS_CATALOG . DIR_WS_IMAGES . $productRecipesItem['recipes_image'])) { ?>
        <a class="productRecipesCardImage" href="<?php echo $productRecipesItemUrl; ?>"><?php echo zen_image(DIR_WS_IMAGES . $productRecipesItem['recipes_image'], $productRecipesItemName, $productRecipesThumbWidth > 0 ? $productRecipesThumbWidth : '', ''); ?></a>
        <?php } ?>
        <div class="productRecipesCardBody card-body">
            <h2 class="productRecipesCardName"><a href="<?php echo $productRecipesItemUrl; ?>"><?php echo $productRecipesItemName; ?></a></h2>
            <?php
            $productRecipesFacts = [];
            if ((int)$productRecipesItem['servings'] > 0) {
                $productRecipesFacts[] = sprintf(TEXT_RECIPES_SERVES, (int)$productRecipesItem['servings']);
            }
            if ((int)$productRecipesItem['prep_minutes'] > 0) {
                $productRecipesFacts[] = sprintf(TEXT_RECIPES_PREP, (int)$productRecipesItem['prep_minutes']);
            }
            if ((int)$productRecipesItem['cook_minutes'] > 0) {
                $productRecipesFacts[] = sprintf(TEXT_RECIPES_COOK, (int)$productRecipesItem['cook_minutes']);
            }
            if ($productRecipesFacts !== []) {
                echo '<p class="productRecipesFacts">' . implode(' &middot; ', $productRecipesFacts) . '</p>';
            }
            if (!empty($productRecipesItemMethods[$productRecipesItemId])) {
                echo '<p class="productRecipesMethods">';
                foreach ($productRecipesItemMethods[$productRecipesItemId] as $productRecipesMethod) {
                    echo '<span class="productRecipesMethod">' . zen_output_string_protected($productRecipesMethod['methods_name']) . '</span> ';
                }
                echo '</p>';
            }
            $productRecipesIntro = ProductRecipesService::plainText((string)$productRecipesItem['recipes_intro'], 200);
            if ($productRecipesIntro !== '') {
                echo '<p class="productRecipesCardIntro">' . zen_output_string_protected($productRecipesIntro) . '</p>';
            }
            echo $productRecipesItemExtra;
            ?>
            <a class="productRecipesView btn btn-sm btn-outline-primary" href="<?php echo $productRecipesItemUrl; ?>"><?php echo TEXT_RECIPES_VIEW; ?></a>
        </div>
    </article>
    <?php } ?>
</div>

<?php if ($productRecipesSplit !== null && (int)$productRecipesSplit->number_of_rows > max(1, (int)ProductRecipesService::config('PRODUCT_RECIPES_PER_PAGE', 12))) { ?>
<div class="productRecipesPaging">
    <div class="productRecipesCount"><?php echo $productRecipesSplit->display_count(TEXT_RECIPES_DISPLAY_COUNT); ?></div>
    <div class="productRecipesLinks"><?php echo $productRecipesSplit->display_links(MAX_DISPLAY_PAGE_LINKS, zen_get_all_get_params(['page', 'info', 'x', 'y'])); ?></div>
</div>
<?php } ?>
<?php } ?>
