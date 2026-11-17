<?php
/**
 * Product Recipes -- the Recipes page (index.php?main_page=recipes).
 *
 *   main_page=recipes                       the index, optionally &method=N, paged with &page=N
 *   main_page=recipes&recipes_id=N          one recipe
 *   ...&recipes_id=N&action=recipes_add     POST: add ingredients to the cart
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

// Core pages load their own lang.<page>.php this way on every release; without it the page's
// text never loads on 1.5.8.
require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');

if (!class_exists('ProductRecipesService', false)) {
    require_once __DIR__ . '/../../../classes/ProductRecipesService.php';
}
ProductRecipesService::defineFallbacks(__DIR__ . '/../../../languages/english/lang.recipes.php');

// Declared global so the template and the observer see them however the page is included.
global $db, $zco_notifier, $breadcrumb, $messageStack, $productRecipesMode, $productRecipesRecipe,
    $productRecipesLines, $productRecipesCanAdd, $productRecipesItems, $productRecipesItemMethods,
    $productRecipesSplit, $productRecipesFilters, $productRecipesMethodId, $productRecipesMeta;

if (!ProductRecipesService::enabled()) {
    zen_redirect(zen_href_link(FILENAME_DEFAULT));
}

$productRecipesId = isset($_GET['recipes_id']) ? (int)$_GET['recipes_id'] : 0;
$productRecipesLanguage = ProductRecipesService::languageId();
$zco_notifier->notify('NOTIFY_HEADER_START_RECIPES', $productRecipesId);

$productRecipesMode = 'index';
$productRecipesRecipe = null;
$productRecipesLines = [];
$productRecipesCanAdd = false;
$productRecipesItems = [];
$productRecipesItemMethods = [];
$productRecipesSplit = null;
$productRecipesFilters = [];
$productRecipesMethodId = 0;
$productRecipesMeta = null;

$breadcrumb->add(NAVBAR_TITLE, zen_href_link(FILENAME_RECIPES));

/**
 * Work out how one ingredient line can be shown and bought.
 *
 * state: 'text'    plain text (no product, product off or gone, or out of stock with Text Only)
 *        'options' linked, but the product has options to choose on its own page
 *        'buy'     linked, with a quantity box and Add to Cart
 *        'link'    linked, but it can't be added here (not for sale, prices hidden, out of stock)
 *
 * @param array<string, mixed> $ingredient
 * @param array<int, array<string, mixed>> $products from getProductsInfo()
 * @return array<string, mixed>
 */
$productRecipesDescribe = static function (array $ingredient, array $products) {
    $line = $ingredient;
    $line['amount_text'] = ProductRecipesService::formatAmount($ingredient['amount']);
    $line['state'] = 'text';
    $line['display_text'] = (string)$ingredient['ingredient_text'];
    $line['href'] = '';
    $line['price_html'] = '';
    $line['stock_text'] = '';
    $line['qty'] = ProductRecipesService::formatAmount($ingredient['cart_qty']);
    if ($line['qty'] === '' || strpos($line['qty'], '/') !== false || strpos($line['qty'], ' ') !== false) {
        $line['qty'] = (string)max(1, (int)ceil((float)$ingredient['cart_qty']));
    }

    $pid = (int)$ingredient['products_id'];
    // A blank ingredient text shows the product's name, even when the product is switched off.
    if ($line['display_text'] === '' && $pid > 0 && isset($products[$pid])) {
        $line['display_text'] = $products[$pid]['name'];
    }
    if ($pid <= 0 || !isset($products[$pid]) || !$products[$pid]['active']) {
        return $line;
    }
    $product = $products[$pid];
    $inStock = $product['quantity'] > 0;
    if (!$inStock && ProductRecipesService::config('PRODUCT_RECIPES_OUT_OF_STOCK', 'Show') === 'Text Only') {
        return $line;
    }

    $line['href'] = zen_href_link(zen_get_info_page($pid), 'products_id=' . $pid);
    if (ProductRecipesService::isOn('PRODUCT_RECIPES_SHOW_PRICES')) {
        $line['price_html'] = zen_get_products_display_price($pid);
    }
    if (ProductRecipesService::isOn('PRODUCT_RECIPES_SHOW_STOCK')) {
        $line['stock_text'] = $inStock ? TEXT_RECIPES_IN_STOCK : TEXT_RECIPES_OUT_OF_STOCK;
    }

    if (zen_has_product_attributes($pid)) {
        $line['state'] = 'options';
        return $line;
    }
    $canBuy = ProductRecipesService::isOn('PRODUCT_RECIPES_ADD_TO_CART')
        && !$product['is_call']
        && zen_get_products_allow_add_to_cart($pid) === 'Y'
        && zen_check_show_prices()
        && ($inStock || STOCK_ALLOW_CHECKOUT === 'true');
    $line['state'] = $canBuy ? 'buy' : 'link';
    return $line;
};

/* ---------------------------------------------------------------------- *
 * Add to cart (POST only)
 * ---------------------------------------------------------------------- */
if ($productRecipesId > 0 && isset($_GET['action']) && $_GET['action'] === 'recipes_add'
    && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $productRecipesBack = zen_href_link(FILENAME_RECIPES, 'recipes_id=' . $productRecipesId);
    $productRecipesBack = str_replace('&amp;', '&', $productRecipesBack);

    // Core already refuses a catalog POST with a bad token; checked again here because this
    // request writes to the cart.
    if (!isset($_POST['securityToken'], $_SESSION['securityToken']) || $_POST['securityToken'] !== $_SESSION['securityToken']) {
        zen_redirect($productRecipesBack);
    }

    $productRecipe = ProductRecipesService::getRecipe($productRecipesId, $productRecipesLanguage);
    if ($productRecipe === null || !ProductRecipesService::isOn('PRODUCT_RECIPES_ADD_TO_CART')) {
        zen_redirect($productRecipesBack);
    }

    // Which ingredient lines: the one whose button was pressed, or every ticked one.
    $productRecipesPicked = [];
    if (isset($_POST['rp_one']) && is_string($_POST['rp_one'])) {
        $productRecipesPicked[] = (int)$_POST['rp_one'];
    } elseif (isset($_POST['rp_pick']) && is_array($_POST['rp_pick'])) {
        foreach (array_keys($_POST['rp_pick']) as $productRecipesKey) {
            $productRecipesPicked[] = (int)$productRecipesKey;
        }
    }
    if ($productRecipesPicked === []) {
        $messageStack->add_session('header', TEXT_RECIPES_NOTHING_CHECKED, 'caution');
        zen_redirect($productRecipesBack);
    }

    // Product ids come from this recipe's own rows, never from the form.
    $productRecipesByLine = [];
    foreach ($productRecipe['ingredients'] as $productRecipesIngredient) {
        $productRecipesByLine[(int)$productRecipesIngredient['ingredients_id']] = $productRecipesIngredient;
    }
    $productRecipesProducts = ProductRecipesService::getProductsInfo(
        array_column($productRecipe['ingredients'], 'products_id'),
        $productRecipesLanguage
    );

    $productRecipesAdd = [];
    $productRecipesSkipped = [];
    foreach (array_unique($productRecipesPicked) as $productRecipesLineId) {
        if (!isset($productRecipesByLine[$productRecipesLineId])) {
            continue;
        }
        $productRecipesLine = $productRecipesDescribe($productRecipesByLine[$productRecipesLineId], $productRecipesProducts);
        $productRecipesPid = (int)$productRecipesLine['products_id'];
        if ($productRecipesPid <= 0) {
            continue; // a text-only line: nothing to add
        }
        $productRecipesQty = (isset($_POST['rp_qty']) && is_array($_POST['rp_qty'])
                && isset($_POST['rp_qty'][$productRecipesLineId]) && is_string($_POST['rp_qty'][$productRecipesLineId]))
            ? trim($_POST['rp_qty'][$productRecipesLineId]) : $productRecipesLine['qty'];

        $productRecipesReason = '';
        if ($productRecipesLine['state'] === 'options') {
            $productRecipesReason = 'attributes';
        } elseif ($productRecipesLine['state'] !== 'buy') {
            $productRecipesReason = (isset($productRecipesProducts[$productRecipesPid]) && $productRecipesProducts[$productRecipesPid]['quantity'] <= 0)
                ? 'stock' : 'unavailable';
        } elseif (!is_numeric($productRecipesQty) || (float)$productRecipesQty <= 0) {
            $productRecipesReason = 'quantity';
        }
        if ($productRecipesReason !== '') {
            $productRecipesSkipped[] = [
                'ingredients_id' => $productRecipesLineId,
                'products_id' => $productRecipesPid,
                'reason' => $productRecipesReason,
            ];
            continue;
        }
        $productRecipesAdd[$productRecipesPid] = ($productRecipesAdd[$productRecipesPid] ?? 0) + (float)$productRecipesQty;
    }

    $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_ADD_TO_CART', $productRecipesId, $productRecipesAdd, $productRecipesSkipped);

    $productRecipesMessages = [
        'attributes' => TEXT_RECIPES_SKIPPED_ATTRIBUTES,
        'stock' => TEXT_RECIPES_SKIPPED_STOCK,
        'quantity' => TEXT_RECIPES_SKIPPED_QTY,
        'unavailable' => TEXT_RECIPES_SKIPPED_UNAVAILABLE,
    ];
    foreach ($productRecipesSkipped as $productRecipesSkip) {
        $productRecipesName = zen_get_products_name((int)$productRecipesSkip['products_id']);
        $productRecipesText = $productRecipesMessages[$productRecipesSkip['reason']] ?? TEXT_RECIPES_SKIPPED_UNAVAILABLE;
        $messageStack->add_session('header', sprintf($productRecipesText, zen_output_string_protected($productRecipesName)), 'caution');
    }

    if ($productRecipesAdd === []) {
        zen_redirect($productRecipesBack);
    }

    // Core's own multiple-add does the rest (min/max/units, stock limits, messages) and
    // redirects. It reads $_POST['products_id'] as products_id => quantity, quantities as strings.
    $_POST['products_id'] = [];
    foreach ($productRecipesAdd as $productRecipesPid => $productRecipesQty) {
        $_POST['products_id'][(int)$productRecipesPid] = (string)$productRecipesQty;
    }
    // To the cart (DISPLAY_CART true) without this page's parameters, or back to the recipe.
    if (DISPLAY_CART === 'true') {
        $_SESSION['cart']->actionMultipleAddProduct(FILENAME_SHOPPING_CART, ['action', 'recipes_id']);
    } else {
        $_SESSION['cart']->actionMultipleAddProduct(FILENAME_RECIPES, ['action']);
    }
    zen_redirect($productRecipesBack);
}

/* ---------------------------------------------------------------------- *
 * One recipe
 * ---------------------------------------------------------------------- */
if ($productRecipesId > 0) {
    $productRecipesRecipe = ProductRecipesService::getRecipe($productRecipesId, $productRecipesLanguage);
    if ($productRecipesRecipe === null) {
        $productRecipesMode = 'notfound';
        http_response_code(404);
    } else {
        $productRecipesMode = 'recipe';
        $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_RECIPE_LOADED', $productRecipesId, $productRecipesRecipe);

        $productRecipesProducts = ProductRecipesService::getProductsInfo(
            array_column($productRecipesRecipe['ingredients'], 'products_id'),
            $productRecipesLanguage
        );
        foreach ($productRecipesRecipe['ingredients'] as $productRecipesIngredient) {
            $productRecipesLine = $productRecipesDescribe($productRecipesIngredient, $productRecipesProducts);
            $productRecipesLine['extra_html'] = '';
            $productRecipesLine['form_fields'] = '';
            $zco_notifier->notify(
                'NOTIFY_PRODUCT_RECIPES_INGREDIENT_LINE',
                $productRecipesLine,
                $productRecipesLine['extra_html'],
                $productRecipesLine['form_fields']
            );
            if ($productRecipesLine['state'] === 'buy') {
                $productRecipesCanAdd = true;
            }
            $productRecipesLines[] = $productRecipesLine;
        }

        $breadcrumb->add(zen_output_string_protected($productRecipesRecipe['recipes_name']));
        $productRecipesMeta = [
            'title' => $productRecipesRecipe['recipes_name'],
            'description' => ProductRecipesService::plainText($productRecipesRecipe['recipes_intro'] !== ''
                ? $productRecipesRecipe['recipes_intro'] : $productRecipesRecipe['recipes_instructions'], 160),
        ];
    }
}

/* ---------------------------------------------------------------------- *
 * The index
 * ---------------------------------------------------------------------- */
if ($productRecipesMode === 'index') {
    $productRecipesMethods = ProductRecipesService::getMethods($productRecipesLanguage);
    $productRecipesMethodId = isset($_GET['method']) ? (int)$_GET['method'] : 0;
    if (!isset($productRecipesMethods[$productRecipesMethodId])) {
        $productRecipesMethodId = 0;
    }

    if (ProductRecipesService::isOn('PRODUCT_RECIPES_SHOW_METHOD_FILTER') && $productRecipesMethods !== []) {
        $productRecipesFilters[] = [
            'href' => zen_href_link(FILENAME_RECIPES),
            'text' => TEXT_RECIPES_FILTER_ALL,
            'active' => $productRecipesMethodId === 0,
        ];
        foreach ($productRecipesMethods as $productRecipesMethod) {
            if ($productRecipesMethod['methods_name'] === '') {
                continue;
            }
            $productRecipesFilters[] = [
                'href' => zen_href_link(FILENAME_RECIPES, 'method=' . (int)$productRecipesMethod['methods_id']),
                'text' => $productRecipesMethod['methods_name'],
                'active' => $productRecipesMethodId === (int)$productRecipesMethod['methods_id'],
            ];
        }
    }
    $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_FILTER_LINKS', [], $productRecipesFilters);

    $productRecipesParts = ProductRecipesService::getListingParts($productRecipesLanguage, $productRecipesMethodId);
    $productRecipesSelect = $productRecipesParts['select'];
    $productRecipesFrom = $productRecipesParts['from'];
    $productRecipesWhere = $productRecipesParts['where'];
    $productRecipesOrderBy = $productRecipesParts['orderBy'];
    $zco_notifier->notify(
        'NOTIFY_PRODUCT_RECIPES_LISTING_QUERY',
        [],
        $productRecipesSelect,
        $productRecipesFrom,
        $productRecipesWhere,
        $productRecipesOrderBy
    );
    $productRecipesQuery = ProductRecipesService::joinListingParts([
        'select' => $productRecipesSelect,
        'from' => $productRecipesFrom,
        'where' => $productRecipesWhere,
        'orderBy' => $productRecipesOrderBy,
    ]);

    $productRecipesPerPage = max(1, (int)ProductRecipesService::config('PRODUCT_RECIPES_PER_PAGE', 12));
    $productRecipesSplit = new splitPageResults($productRecipesQuery, $productRecipesPerPage, 'r.recipes_id', 'page');
    $productRecipesResult = $db->Execute($productRecipesSplit->sql_query);
    while (!$productRecipesResult->EOF) {
        $productRecipesItems[] = $productRecipesResult->fields;
        $productRecipesResult->MoveNext();
    }
    $productRecipesItemMethods = ProductRecipesService::getMethodsForRecipes(
        array_column($productRecipesItems, 'recipes_id'),
        $productRecipesLanguage
    );

    if ($productRecipesMethodId > 0) {
        $breadcrumb->add(zen_output_string_protected($productRecipesMethods[$productRecipesMethodId]['methods_name']));
    }
    $productRecipesMeta = [
        'title' => ($productRecipesMethodId > 0)
            ? HEADING_TITLE_RECIPES . ': ' . $productRecipesMethods[$productRecipesMethodId]['methods_name']
            : HEADING_TITLE_RECIPES,
        'description' => ProductRecipesService::plainText(TEXT_RECIPES_PAGE_INTRO, 160),
    ];
}

$zco_notifier->notify('NOTIFY_HEADER_END_RECIPES', $productRecipesId);
