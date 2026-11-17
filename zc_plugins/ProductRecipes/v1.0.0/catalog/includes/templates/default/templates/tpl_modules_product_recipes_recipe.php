<?php
/**
 * Product Recipes -- one recipe.
 *
 * In: $productRecipesRecipe (with 'ingredients' and 'methods'), $productRecipesLines (each
 *     ingredient with 'state' text|link|options|buy, 'display_text', 'amount_text', 'href',
 *     'price_html', 'stock_text', 'qty', 'extra_html', 'form_fields'), $productRecipesCanAdd.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
$productRecipesR = $productRecipesRecipe;
$productRecipesRid = (int)$productRecipesR['recipes_id'];
$productRecipesName = zen_output_string_protected($productRecipesR['recipes_name']);
?>
<h1 id="productRecipesHeading"><?php echo $productRecipesName; ?></h1>

<div class="productRecipesTop">
    <?php if ($productRecipesR['recipes_image'] !== '' && is_file(DIR_FS_CATALOG . DIR_WS_IMAGES . $productRecipesR['recipes_image'])) { ?>
    <div class="productRecipesImage"><?php echo zen_image(DIR_WS_IMAGES . $productRecipesR['recipes_image'], $productRecipesName, '', ''); ?></div>
    <?php } ?>
    <div class="productRecipesSummary">
        <?php
        $productRecipesFacts = [];
        if ((int)$productRecipesR['servings'] > 0) {
            $productRecipesFacts[] = sprintf(TEXT_RECIPES_SERVES, (int)$productRecipesR['servings']);
        }
        if ((int)$productRecipesR['prep_minutes'] > 0) {
            $productRecipesFacts[] = sprintf(TEXT_RECIPES_PREP, (int)$productRecipesR['prep_minutes']);
        }
        if ((int)$productRecipesR['cook_minutes'] > 0) {
            $productRecipesFacts[] = sprintf(TEXT_RECIPES_COOK, (int)$productRecipesR['cook_minutes']);
        }
        if ($productRecipesFacts !== []) {
            echo '<p class="productRecipesFacts">' . implode(' &middot; ', $productRecipesFacts) . '</p>';
        }
        if (!empty($productRecipesR['methods'])) {
            echo '<p class="productRecipesMethods">';
            foreach ($productRecipesR['methods'] as $productRecipesMethod) {
                echo '<a class="productRecipesMethod" href="' . zen_href_link(FILENAME_RECIPES, 'method=' . (int)$productRecipesMethod['methods_id']) . '">'
                    . zen_output_string_protected($productRecipesMethod['methods_name']) . '</a> ';
            }
            echo '</p>';
        }
        if ($productRecipesR['recipes_intro'] !== '') {
            echo '<div class="productRecipesIntro">' . nl2br(zen_output_string_protected($productRecipesR['recipes_intro'])) . '</div>';
        }
        ?>
    </div>
</div>

<?php $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_TPL_BEFORE_INGREDIENTS', $productRecipesR); ?>

<?php if ($productRecipesLines !== []) { ?>
<section class="productRecipesIngredients">
    <h2><?php echo TEXT_RECIPES_INGREDIENTS; ?></h2>
    <?php
    if ($productRecipesCanAdd) {
        echo zen_draw_form('productRecipesCart', zen_href_link(FILENAME_RECIPES, 'recipes_id=' . $productRecipesRid . '&action=recipes_add', $request_type), 'post');
        ?>
        <p class="productRecipesAddHelp"><?php echo TEXT_RECIPES_ADD_HELP; ?></p>
        <?php // First submit button in the form, so pressing Enter in a quantity box adds the checked lines. ?>
        <p class="productRecipesAddAll"><button type="submit" name="rp_all" value="1" class="btn btn-primary button_in_cart"><?php echo TEXT_RECIPES_ADD_CHECKED; ?></button></p>
        <?php
    }
    ?>
    <ul class="productRecipesList">
        <?php foreach ($productRecipesLines as $productRecipesLine) {
            $productRecipesLid = (int)$productRecipesLine['ingredients_id'];
            $productRecipesText = zen_output_string_protected($productRecipesLine['display_text']);
            $productRecipesLabel = trim($productRecipesLine['amount_text'] . ' ' . zen_output_string_protected($productRecipesLine['unit']));
            ?>
        <li class="productRecipesLine productRecipesLine-<?php echo $productRecipesLine['state']; ?>">
            <span class="productRecipesWhat">
                <?php if ($productRecipesLine['state'] === 'buy') { ?>
                <input type="checkbox" id="rpPick<?php echo $productRecipesLid; ?>" name="rp_pick[<?php echo $productRecipesLid; ?>]" value="1"<?php echo empty($productRecipesLine['optional']) ? ' checked' : ''; ?>>
                <label for="rpPick<?php echo $productRecipesLid; ?>">
                <?php } ?>
                <?php if ($productRecipesLabel !== '') { ?><span class="productRecipesAmount"><?php echo $productRecipesLabel; ?></span> <?php } ?>
                <?php if ($productRecipesLine['href'] !== '') { ?>
                <a class="productRecipesProduct" href="<?php echo $productRecipesLine['href']; ?>"><?php echo $productRecipesText; ?></a>
                <?php } else { ?>
                <span class="productRecipesText"><?php echo $productRecipesText; ?></span>
                <?php } ?>
                <?php if (!empty($productRecipesLine['optional'])) { ?><span class="productRecipesOptional"><?php echo TEXT_RECIPES_OPTIONAL; ?></span><?php } ?>
                <?php if ($productRecipesLine['state'] === 'buy') { ?>
                </label>
                <?php } ?>
            </span>
            <?php if ($productRecipesLine['price_html'] !== '' || $productRecipesLine['stock_text'] !== '') { ?>
            <span class="productRecipesShop">
                <?php if ($productRecipesLine['price_html'] !== '') { ?><span class="productRecipesPrice"><?php echo $productRecipesLine['price_html']; ?></span><?php } ?>
                <?php if ($productRecipesLine['stock_text'] !== '') { ?><span class="productRecipesStock"><?php echo $productRecipesLine['stock_text']; ?></span><?php } ?>
            </span>
            <?php } ?>
            <?php if ($productRecipesLine['state'] === 'buy') { ?>
            <span class="productRecipesBuy">
                <label class="productRecipesQtyLabel" for="rpQty<?php echo $productRecipesLid; ?>"><?php echo TEXT_RECIPES_QTY; ?></label>
                <input class="productRecipesQty" type="text" inputmode="decimal" size="3" id="rpQty<?php echo $productRecipesLid; ?>" name="rp_qty[<?php echo $productRecipesLid; ?>]" value="<?php echo zen_output_string_protected($productRecipesLine['qty']); ?>" aria-label="<?php echo zen_output_string_protected(sprintf(TEXT_RECIPES_QTY_FOR, $productRecipesLine['display_text'])); ?>">
                <button type="submit" name="rp_one" value="<?php echo $productRecipesLid; ?>" class="btn btn-sm btn-outline-primary button_in_cart"><?php echo TEXT_RECIPES_ADD_ONE; ?></button>
                <?php echo $productRecipesLine['form_fields']; ?>
            </span>
            <?php } elseif ($productRecipesLine['state'] === 'options') { ?>
            <span class="productRecipesBuy">
                <a class="productRecipesOptions btn btn-sm btn-outline-primary" href="<?php echo $productRecipesLine['href']; ?>"><?php echo TEXT_RECIPES_CHOOSE_OPTIONS; ?></a>
            </span>
            <?php } ?>
            <?php echo $productRecipesLine['extra_html']; ?>
        </li>
        <?php } ?>
    </ul>
    <?php if ($productRecipesCanAdd) { ?>
    <p class="productRecipesAddAll"><button type="submit" name="rp_all" value="1" class="btn btn-primary button_in_cart"><?php echo TEXT_RECIPES_ADD_CHECKED; ?></button></p>
    </form>
    <?php } ?>
</section>
<?php } ?>

<?php $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_TPL_AFTER_INGREDIENTS', $productRecipesR); ?>

<?php if ($productRecipesR['recipes_instructions'] !== '') { ?>
<section class="productRecipesInstructions">
    <h2><?php echo TEXT_RECIPES_INSTRUCTIONS; ?></h2>
    <?php // Admin-written HTML, trusted the same way as a product description. ?>
    <div class="productRecipesSteps"><?php echo $productRecipesR['recipes_instructions']; ?></div>
</section>
<?php } ?>

<?php $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_TPL_AFTER_INSTRUCTIONS', $productRecipesR); ?>

<?php if ($productRecipesR['recipes_notes'] !== '') { ?>
<section class="productRecipesNotes">
    <h2><?php echo TEXT_RECIPES_NOTES; ?></h2>
    <p><?php echo nl2br(zen_output_string_protected($productRecipesR['recipes_notes'])); ?></p>
</section>
<?php } ?>

<p class="productRecipesBack"><a href="<?php echo zen_href_link(FILENAME_RECIPES); ?>"><?php echo TEXT_RECIPES_BACK; ?></a></p>
