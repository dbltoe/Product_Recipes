# Product Recipes v1.0.0: design (frozen 2026-11-16)

This is the build spec for the free plugin. `F:\Recipes\PLAN.md` holds the why; this file holds
the what. Anything Product Recipes Pro needs from Free is in section 6 and is part of v1.0.0.

Range: Zen Cart 1.5.8 through 3.0.0, PHP 7.4 through 8.5, one codebase. Everything lives in
`zc_plugins/ProductRecipes/v1.0.0/`. No core file is edited or copied.

## 1. Tables

All names are `TABLE_*` constants built on `DB_PREFIX`, defined (guarded) in both
`admin/includes/extra_datafiles/` and `catalog/includes/extra_datafiles/`. Engine InnoDB, charset
`DB_CHARSET` (fallback `utf8mb4`). Created with `CREATE TABLE IF NOT EXISTS`, so an upgrade
re-runs install safely.

| Constant / table | Columns |
|---|---|
| `TABLE_PRODUCT_RECIPES` `product_recipes` | `recipes_id` int AI PK; `recipes_status` tinyint(1) default 1; `sort_order` int default 0; `servings` smallint default 0; `prep_minutes` smallint default 0; `cook_minutes` smallint default 0; `recipes_image` varchar(255) default ''; `date_added` datetime; `last_modified` datetime NULL. KEY (`recipes_status`, `sort_order`) |
| `TABLE_PRODUCT_RECIPES_DESCRIPTION` `product_recipes_description` | `recipes_id`, `language_id` (PK both); `recipes_name` varchar(255); `recipes_intro` text; `recipes_instructions` mediumtext (HTML); `recipes_notes` text |
| `TABLE_PRODUCT_RECIPES_INGREDIENTS` `product_recipes_ingredients` | `ingredients_id` int AI PK; `recipes_id` int; `sort_order` int; `products_id` int default 0 (0 = text only); `amount` decimal(10,3) NULL; `cart_qty` decimal(10,3) default 1; `optional` tinyint(1) default 0. KEY (`recipes_id`, `sort_order`), KEY (`products_id`) |
| `TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION` `product_recipes_ingredients_description` | `ingredients_id`, `language_id` (PK both); `ingredient_text` varchar(255); `unit` varchar(32) |
| `TABLE_PRODUCT_RECIPES_METHODS` `product_recipes_methods` | `methods_id` int AI PK; `sort_order` int; `methods_status` tinyint(1) default 1 |
| `TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION` `product_recipes_methods_description` | `methods_id`, `language_id` (PK both); `methods_name` varchar(64) |
| `TABLE_PRODUCT_RECIPES_TO_METHODS` `product_recipes_to_methods` | `recipes_id`, `methods_id` (PK both); KEY (`methods_id`) |

`unit` sits with the per-language text ("cup" / "taza"). `amount` is a number so Pro can scale it.

**Seed:** when `product_recipes_methods` is empty, insert Microwave (10), Bake (20), No Bake (30),
with the English name in every installed language. Never re-seeded on upgrade.

**Uninstall** drops all seven tables, the configuration group and keys, both admin pages and the
sidebox's `layout_boxes` rows. Recipe images in `images/recipes/` are left in place (they're the
store owner's files); the README says so.

## 2. Configuration (group "Product Recipes", page key `configProductRecipes`)

Values `true`/`false` lowercase, like Attribute Pricing Manager. INSERT IGNORE, then UPDATE the
title, description, sort and set_function (never the value).

| Key | Default | Meaning |
|---|---|---|
| `PRODUCT_RECIPES_STATUS` | true | Master switch. Off: the page redirects home, no links, no sidebox content. Admin unaffected. |
| `PRODUCT_RECIPES_PER_PAGE` | 12 | Recipes per page on the index. |
| `PRODUCT_RECIPES_SHOW_PRICES` | true | Show each linked product's price (still subject to the store's own price-visibility rules). |
| `PRODUCT_RECIPES_SHOW_STOCK` | true | Show In Stock / Out of Stock on linked lines. |
| `PRODUCT_RECIPES_ADD_TO_CART` | true | Show quantity boxes, per-line Add to Cart and "Add Checked to Cart". |
| `PRODUCT_RECIPES_OUT_OF_STOCK` | Show | `Show`: link and price stay, add-to-cart only if the store allows checkout of out-of-stock items. `Text Only`: the line shows as plain text. |
| `PRODUCT_RECIPES_SHOW_METHOD_FILTER` | true | Microwave / Bake / No Bake filter links on the index. |
| `PRODUCT_RECIPES_INFO_SIDEBOX_LINK` | true | Add a Recipes link to the Information sidebox (Zen Cart 2.0.0 and later; 1.5.8 has no hook). |
| `PRODUCT_RECIPES_SIDEBOX_MAX` | 3 | Latest recipes listed in the Recipes sidebox. |
| `PRODUCT_RECIPES_IMAGE_WIDTH` | 220 | Thumbnail width on the index, in pixels. |

## 3. Admin: Extras > Recipes (`cmd=product_recipes`, page key `productRecipes`)

One page, actions by `action=`. Every change is a POST (admin `application_top` checks the
`securityToken`); GETs only display. `$pages`/`$page` are never used as variable names (core's
header navigation overwrites them). Links from `zen_href_link()` are `&amp;`-joined: echoed as is,
decoded before `zen_redirect()`.

| Action | Method | What it does |
|---|---|---|
| (none) | GET | List: search `q`, method filter `m`, paging `rp_page`, status toggle and Edit / Copy / Delete per row. |
| `new`, `edit&rID=N` | GET | The recipe form. |
| `save` | POST | Insert or update the recipe, its descriptions, ingredients, methods and image. With `rp_find` set it re-renders the posted form plus product search results instead of saving; with `rp_more` it adds five blank rows. |
| `status` | POST | Flip `recipes_status`. |
| `copy` | POST | Duplicate a recipe (status off, name + " (copy)"). |
| `delete` | GET confirm, POST do | Delete the recipe, its rows and links. |
| `methods` | GET | Cooking Methods list. |
| `save_methods` | POST | Rename, re-sort, turn on/off, add one. |
| `delete_method` | POST | Delete a method and its recipe links. |

**Product picker (no AJAX):** each ingredient row has a Product field that takes a product ID or
model. Saved rows show the resolved product name, status and attributes note beside it. A "Find
a product" box above the rows posts the form back (`rp_find`) and lists matches (ID, model, name,
price, status); a small script fills the last-focused row when a result's "Use" is clicked, and
typing the ID by hand always works.

**Image:** upload (jpg, jpeg, png, gif, webp) to `images/recipes/` through core's `upload` class
(the folder is created if missing), or keep the current one, or remove the link.

**Sanitizer** (`admin/includes/extra_datafiles/product_recipes_sanitizer.php`, page-scoped to
`product_recipes`), matching core's own fields: `PRODUCT_NAME_DEEP_REGEX` for `recipes_name`
(as core's `products_name`), `PRODUCT_DESC_REGEX` for `recipes_intro`, `recipes_instructions`,
`recipes_notes` (as `products_description`; one level, keyed by language id);
`WORDS_AND_SYMBOLS_REGEX` for `q` (GET) and `rp_find_q` (POST); `NULL_ACTION` for the nested
`ing` and `method_name` arrays, which the page validates and escapes itself.

**Product deleted in Catalog:** `NOTIFIER_ADMIN_ZEN_REMOVE_PRODUCT` (fired before core deletes the
product's descriptions) first copies the product's name, per language, into any of its ingredient
lines whose text was left blank, then sets `products_id = 0`; the lines stay as text. On the
storefront a switched-off product's line shows its name as plain text when the text is blank.

**Language added or deleted:** `NOTIFY_ADMIN_LANGUAGE_INSERT` copies recipe, ingredient and
method text from the admin's current language into the new one (`INSERT IGNORE ... SELECT`), as
core's `languages.php` does for products; `NOTIFY_ADMIN_LANGUAGE_DELETE` deletes that language's
rows from the three description tables.

## 4. Storefront

`main_page=recipes`. `header_php.php` starts with
`require DIR_WS_MODULES . zen_get_module_directory('require_languages.php');`.

- **Index:** active recipes in the current language, `method=N` filter, `splitPageResults` paging,
  card per recipe (thumbnail, name, intro trimmed to 200 characters, method badges).
- **Recipe** (`recipes_id=N`): name, image, servings/prep/cook line, method badges, intro,
  ingredients, instructions (stored HTML, output as is, like a product description), notes
  (escaped, line breaks kept). An inactive or missing recipe shows "Recipe not found" with a link
  to the index and sends HTTP 404.
- **Ingredient line:** checkbox `rp_pick[ingredientsId]` (checked unless optional), amount (trailing
  zeros trimmed) + unit + text. With a linked, active product: the text links to the product page,
  then price (`zen_get_products_display_price()`), stock (`zen_get_products_stock()`), quantity
  box `rp_qty[ingredientsId]` (default `cart_qty`), and a per-line `<button name="rp_one"
  value="ingredientsId">`. A product with attributes to choose gets "Choose options" linking to its
  page instead of a box. An inactive product turns the line to plain text.
- **Add to cart:** POST `main_page=recipes&recipes_id=N&action=recipes_add` with `securityToken`.
  Product ids come from the database row for each posted `ingredients_id` of THIS recipe, never
  from the form. Lines are dropped when the product is inactive, has attributes to choose, fails
  `zen_get_products_allow_add_to_cart()`, prices aren't shown to this visitor
  (`zen_check_show_prices()`), or (Text Only setting) it's out of stock. Two lines for one product
  add up. Then `NOTIFY_PRODUCT_RECIPES_ADD_TO_CART`, skipped lines go to the message stack, and the
  clean list goes to core's `$_SESSION['cart']->actionMultipleAddProduct($goto, ['action'])`
  through `$_POST['products_id']`; `$goto` is the cart when `DISPLAY_CART` is true, else the recipe.
  Nothing to add: redirect back to the recipe with the messages.
- **Meta tags:** `NOTIFY_MODULE_META_TAGS_UNSPECIFIEDPAGE` sets the override flag, the title (recipe
  name, or the page heading on the index) and the description (intro, plain text, 160 characters).
- **Stylesheet:** `product_recipes.css`, linked by the observer on `NOTIFY_HTML_HEAD_END` only when
  `$current_page_base` is `recipes`; path from `__DIR__`.
- **Information sidebox link:** `NOTIFY_INFORMATION_SIDEBOX_ADDITIONS` (2.0.0+), with the
  list-group classes Bootstrap templates use.
- **Recipes sidebox** (`product_recipes.php`): latest N active recipes, the method links, an "All
  Recipes" link. Core's box wrapper.
- **Languages:** page strings in `lang.recipes.php`, sidebox and link strings in
  `extra_definitions/lang.product_recipes.php`. After each load, missing keys are defined from the
  English file (1.5.8 and 2.0.x have no English fallback for plugins).

## 5. Classes and files

- `catalog/includes/classes/ProductRecipesService.php`: plain class, every query. Required through
  `__DIR__` behind `class_exists('ProductRecipesService', false)` from the page, the sidebox, the
  observers and the admin page. Public, stable from v1.0.0: `getListing()`, `getRecipe()`,
  `getIngredients()`, `getMethods()`, `getMethodsForRecipes()`, `getRecipesForProduct()`,
  `getLatest()`, `formatAmount()`, `defineFallbacks()`.
- Observers: `catalog/.../observers/auto.product_recipes.php` (`zcObserverProductRecipes`) and
  `admin/.../observers/auto.product_recipes_admin.php` (`zcObserverProductRecipesAdmin`). No
  auto_loader entries.
- Every `require` of a plugin class is behind `class_exists(X, false)` (upgrade redeclare trap).

## 6. Notifiers Free fires (the Pro contract)

| Notifier | Where | Parameters |
|---|---|---|
| `NOTIFY_PRODUCT_RECIPES_ADMIN_FORM_EXTRA` | recipe form | `$recipesId`, `&$extraInputs` (list of `['label' => html, 'input' => html]`) |
| `NOTIFY_PRODUCT_RECIPES_ADMIN_INGREDIENT_ROW_EXTRA` | each ingredient row | `$row` (plus `index` and resolved `products_id`), `&$extraCells` (list of html, drawn in a full-width row under the ingredient) |
| `NOTIFY_PRODUCT_RECIPES_ADMIN_SAVE_END` | after save | `$recipesId`, `&$context` (`['action' => 'insert'/'update', 'ingredient_ids' => [row key => ingredients_id]]`) |
| `NOTIFY_PRODUCT_RECIPES_ADMIN_DELETE` | before delete | `$recipesId` |
| `NOTIFY_PRODUCT_RECIPES_ADMIN_COPY` | after copy | `$oldRecipesId`, `&$newRecipesId`, `&$ingredientMap` (old id => new id) |
| `NOTIFY_HEADER_START_RECIPES` / `NOTIFY_HEADER_END_RECIPES` | header_php | `$recipesId` (0 on the index) |
| `NOTIFY_PRODUCT_RECIPES_LISTING_QUERY` | index | `[]`, `&$select`, `&$from`, `&$where`, `&$orderBy` |
| `NOTIFY_PRODUCT_RECIPES_FILTER_LINKS` | index | `[]`, `&$filterLinks` (list of `['href', 'text', 'active']`) |
| `NOTIFY_PRODUCT_RECIPES_RECIPE_LOADED` | recipe | `$recipesId`, `&$recipe` (with `ingredients` and `methods`) |
| `NOTIFY_PRODUCT_RECIPES_INGREDIENT_LINE` | each line | `$ingredient`, `&$extraHtml`, `&$formFields` |
| `NOTIFY_PRODUCT_RECIPES_ADD_TO_CART` | add | `$recipesId`, `&$lines` (`products_id => qty`), `&$skipped` (list of `['ingredients_id', 'products_id', 'reason']`) |
| `NOTIFY_PRODUCT_RECIPES_TPL_BEFORE_INGREDIENTS`, `NOTIFY_PRODUCT_RECIPES_TPL_AFTER_INGREDIENTS`, `NOTIFY_PRODUCT_RECIPES_TPL_AFTER_INSTRUCTIONS` | recipe template | `$recipe` |
| `NOTIFY_PRODUCT_RECIPES_TPL_BEFORE_LISTING` | index, after the filter links | recipe count, `&$noneText` |
| `NOTIFY_PRODUCT_RECIPES_TPL_LISTING_ITEM` | index card | `$item`, `&$extraHtml` |

A Pro observer that adds attribute lines itself takes them out of `$skipped` (reason `attributes`)
and calls `add_cart()` with the attributes; Free never adds a product with attributes.

## 7. Security rules

- Every DB value through `(int)`, `zen_db_input()` or `zen_db_perform()`.
- Output escaped with `zen_output_string_protected()`, except recipe instructions (admin-authored
  HTML, same trust as a product description).
- The admin page 404s unless `IS_ADMIN_FLAG` is true, and the ReadMe page is superusers only.
- Storefront writes only the visitor's own cart, only on POST with a valid `securityToken`.
- No call-home, no external requests, no `eval`, no shell.
