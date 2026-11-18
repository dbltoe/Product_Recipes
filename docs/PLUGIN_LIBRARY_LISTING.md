# Plugins Library Listing

Not packaged; the text pasted into the Zen Cart Plugins Library submission form, 2026-11-17.
Committed so the wording is versioned with the code. The Description and Changelog are plain
text (the form doesn't render Markdown), with "- " bullets.

**Name:** Product Recipes

**Type:** Other Modules

**Forum thread:** https://www.zen-cart.com/threads/207509#post-1348069

**Zen Cart Versions:** 1.5.8, 2.0.0, 2.0.1, 2.1.0, 2.2.0, 2.2.1, 2.2.2, 2.3.0, 3.0.0

**Encapsulated:** Yes (zc_plugins). The Library writes the pluginId into manifest.php when the release is published.

**Release Version:** v1.0.0 (matches `pluginVersion` in manifest.php)

**ZIP:** product_recipes_v1.0.0.zip (sha256 e5946841cc220877bebb5b372b1ca0b3dad584308508c8f12149d51a30bc5ded)

## Description

```text
Product Recipes adds a Recipes page to your store, where each ingredient can be a product you sell. Shoppers read the recipe and add what they need to the cart from the same page.

What shoppers see:

- A Recipes page listing your recipes with a picture, servings and times, filtered by cooking method.
- Each recipe has its ingredients, the steps and your notes. An ingredient linked to a product shows the product's current price and stock, links to its product page, and has its own Add to Cart button.
- Add Checked to Cart: shoppers tick the ingredients they need and add them all at once.
- A product with options to choose (size, flavor) shows Choose Options and opens its product page, because only the shopper can choose them.
- Every recipe prints cleanly; the cart buttons and filters drop out of the printout.

Adding to the cart goes through Zen Cart's own cart, so minimums, maximums, quantity units and your stock rules apply exactly as they do on a product page. Products that are call-for-price, out of stock (when your store doesn't sell out-of-stock items), or whose prices the visitor can't see are shown but not offered for the cart.

Writing recipes:

- Everything is on Extras > Recipes: name, introduction, picture, servings, prep and cook times, the steps in your admin's HTML editor, notes, and the ingredient rows.
- Link an ingredient by product ID or model, or use Find a product to look it up by name. Ingredients that aren't products ("salt to taste") are plain text.
- Amounts can be written the way recipes write them: 1 1/2, 1/2, 0.75.
- Microwave, Bake and No Bake cooking methods are ready when you install. Rename them, add your own (Grill, Slow Cooker) or turn any off. A recipe can have more than one.
- Several languages are supported, with one box per language for every text field.

Also included:

- A Recipes sidebox (newest recipes and the cooking methods), turned on in Tools > Layout Boxes Controller. On Zen Cart 2.0.0 and later, a Recipes link is also added to the Information sidebox.
- Each recipe page gets its own page title and description.
- Every storefront file can be overridden from your own template, and the plugin fires notifiers other plugins can use. The list is in docs/CUSTOMIZING.md.
- No core file and no template file is edited. Everything lives in zc_plugins/ProductRecipes/.

Requirements: Zen Cart 1.5.8 through 3.0.0, PHP 7.4 through 8.5.

Installing: copy zc_plugins/ProductRecipes into your store's zc_plugins folder and install it under Modules > Plugin Manager. Then go to Extras > Recipes and click New Recipe. The storefront page is index.php?main_page=recipes, and the settings are under Configuration > Product Recipes.

Uninstalling deletes every recipe, ingredient and cooking method, so back up your database first if you might want them again. Pictures in images/recipes/ stay on the server.

The full guide is README.md in the zip. Once the plugin is installed, the ReadMe button in Plugin Manager opens it for superusers.

GNU General Public License v2.0.
```

## Changelog (v1.0.0)

```text
First release.

- Recipes page (index.php?main_page=recipes) with a paged index, a cooking-method filter, and one page per recipe.
- Ingredients can link to products: live price and stock, a link to the product page, per-line Add to Cart and Add Checked to Cart. Adding goes through Zen Cart's own cart, so quantity rules and stock rules apply. Products with options link to their product page instead.
- Cooking methods: Microwave, Bake and No Bake to start with. Rename, add, turn off or delete them in Extras > Recipes > Cooking Methods. A recipe can have several.
- Extras > Recipes: list, search, filter, add, edit, copy, delete, turn on and off. Product finder (no AJAX). Kitchen-fraction amounts (1 1/2). Picture upload to images/recipes/. Instructions in the admin's HTML editor. One box per language for every text; a language added later gets a copy of the text to translate.
- Recipes sidebox and, on Zen Cart 2.0.0 and later, a link in the Information sidebox.
- Page title and description for each recipe. Print-friendly recipe pages.
- Notifiers for other plugins: admin form and save, copy, delete, index query, filter links, recipe and ingredient lines, add to cart, three template slots.
- Zen Cart 1.5.8 through 3.0.0, PHP 7.4 through 8.5. Encapsulated: no core or template file changed.
```
