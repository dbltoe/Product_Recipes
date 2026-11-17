# Customizing Product Recipes

## Template files

Copy any of these from `zc_plugins/ProductRecipes/v1.0.0/catalog/includes/templates/default/` to the same subfolder of `includes/templates/<your template>/` and edit the copy. Zen Cart uses your template's copy first.

| File | What it draws |
|---|---|
| `templates/tpl_recipes_default.php` | Picks the index or the recipe template. |
| `templates/tpl_modules_product_recipes_listing.php` | The index: heading, filter links, recipe cards, paging. |
| `templates/tpl_modules_product_recipes_recipe.php` | One recipe: picture, facts, methods, ingredients form, instructions, notes. |
| `sideboxes/tpl_product_recipes.php` | The Recipes sidebox content. |

## Styles

`product_recipes.css` loads on the Recipes page only. Everything sits inside `#productRecipes`, with `productRecipes...` class names: `productRecipesFilters`, `productRecipesGrid`, `productRecipesCard`, `productRecipesList`, `productRecipesLine` (plus `productRecipesLine-buy`, `-options`, `-link` or `-text` for the line's state), `productRecipesPrice`, `productRecipesStock`, `productRecipesQty`, `productRecipesSteps`, `productRecipesNotes`. Put your changes in your template's stylesheet.

## Text

Page text is in `catalog/includes/languages/english/lang.recipes.php`. Sidebox and link text is in `catalog/includes/languages/english/extra_definitions/lang.product_recipes.php`. Admin text is in `admin/includes/languages/english/`. Set `TEXT_RECIPES_PAGE_INTRO` to put a paragraph at the top of the index.

## Notifiers

Fired with Zen Cart's `$zco_notifier`. Parameters after the first are passed by reference where marked `&`.

| Notifier | Where | Parameters |
|---|---|---|
| `NOTIFY_PRODUCT_RECIPES_ADMIN_FORM_EXTRA` | recipe form | `$recipesId`, `&$extraInputs` (list of `['label' => html, 'input' => html]`) |
| `NOTIFY_PRODUCT_RECIPES_ADMIN_INGREDIENT_ROW_EXTRA` | each ingredient row | `$row` (the row's fields plus `index`, its posted row index, and `products_id`, the product it links to or 0), `&$extraCells` (list of html, drawn in a full-width row under the ingredient) |
| `NOTIFY_PRODUCT_RECIPES_ADMIN_SAVE_END` | after save | `$recipesId`, `&$context` (`['action' => 'insert' or 'update', 'ingredient_ids' => [posted row index => ingredients_id]]`) |
| `NOTIFY_PRODUCT_RECIPES_ADMIN_DELETE` | before delete | `$recipesId` |
| `NOTIFY_PRODUCT_RECIPES_ADMIN_COPY` | after copy | `$oldRecipesId`, `&$newRecipesId`, `&$ingredientMap` (old ingredients_id => new) |
| `NOTIFY_HEADER_START_RECIPES`, `NOTIFY_HEADER_END_RECIPES` | storefront page | `$recipesId` (0 on the index) |
| `NOTIFY_PRODUCT_RECIPES_LISTING_QUERY` | index | `[]`, `&$select`, `&$from`, `&$where`, `&$orderBy` |
| `NOTIFY_PRODUCT_RECIPES_FILTER_LINKS` | index | `[]`, `&$filterLinks` (list of `['href', 'text', 'active']`) |
| `NOTIFY_PRODUCT_RECIPES_RECIPE_LOADED` | recipe | `$recipesId`, `&$recipe` (with `ingredients` and `methods`) |
| `NOTIFY_PRODUCT_RECIPES_INGREDIENT_LINE` | each ingredient line | `$line`, `&$extraHtml`, `&$formFields` |
| `NOTIFY_PRODUCT_RECIPES_ADD_TO_CART` | add to cart, before core's cart | `$recipesId`, `&$lines` (products_id => quantity), `&$skipped` (list of `['ingredients_id', 'products_id', 'reason']`, reason `attributes`, `stock`, `quantity` or `unavailable`) |
| `NOTIFY_PRODUCT_RECIPES_TPL_BEFORE_INGREDIENTS`, `NOTIFY_PRODUCT_RECIPES_TPL_AFTER_INGREDIENTS`, `NOTIFY_PRODUCT_RECIPES_TPL_AFTER_INSTRUCTIONS` | recipe template | `$recipe` |
| `NOTIFY_PRODUCT_RECIPES_TPL_BEFORE_LISTING` | index, after the filter links (echo to draw) | the number of recipes on the page, `&$noneText` (the message shown when there are none) |
| `NOTIFY_PRODUCT_RECIPES_TPL_LISTING_ITEM` | each index card | `$item`, `&$extraHtml` |

Product Recipes never adds a product that has options. An observer that wants to (with options it knows) takes the line out of `$skipped` and adds it itself with the cart's `add_cart()`.

## Service class

`ProductRecipesService` (in `catalog/includes/classes/`) holds every query. Its public static methods are stable from 1.0.0: `getListingParts()`, `joinListingParts()`, `getRecipe()`, `getIngredients()`, `getLatest()`, `getRecipesForProduct()`, `getMethods()`, `getMethodsForRecipes()`, `getProductsInfo()`, `resolveProductRef()`, `findProducts()`, `formatAmount()`, `parseAmount()`, `plainText()`, `defineFallbacks()`, plus `config()`, `isOn()`, `enabled()` and `languageId()`. Require it by path behind `class_exists('ProductRecipesService', false)`.
