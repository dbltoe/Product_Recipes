# Product Recipes

Version 1.0.0 · Zen Cart 1.5.8 through 3.0.0 · PHP 7.4 through 8.5 · GPL v2 · by My Zen Cart Host (dbltoe)

Recipes that sell the ingredients you stock.

## What it does

Product Recipes adds a Recipes page to your store. You write the recipes in your admin, and each ingredient can be linked to a product you sell. On the recipe, a linked ingredient shows the product's current price and stock, links to its product page, and has its own Add to Cart button. Shoppers can also tick the ingredients they need and add them all at once.

```
  Ingredients                                   [ Add Checked to Cart ]

  [x] 2 cups  Stone-Ground Cornmeal    $6.50  In stock   Qty [1] [ Add to Cart ]
  [x] 1 jar   Bourbon Worcestershire  $17.00  In stock   Qty [1] [ Add to Cart ]
      1 tsp   salt
  [ ] 1/2 cup Smoked Pecans (optional) $9.95  In stock   Qty [1] [ Add to Cart ]
```

- Recipes are grouped by cooking method. **Microwave**, **Bake** and **No Bake** are ready when you install; rename them, add your own (Grill, Slow Cooker...) or turn any off. A recipe can have more than one.
- Ingredients that aren't products ("salt to taste") are plain text.
- Amounts can be written the way recipes write them: `1 1/2`, `1/2`, `0.75`.
- Each recipe has a picture, servings, prep and cook times, a short introduction, the steps (in your admin's HTML editor) and notes.
- Every recipe prints cleanly: the cart buttons and filters drop out of the printout.
- Several languages are supported: every text field has one box per language. When you add a language in Localization > Languages, every recipe, ingredient and cooking method gets a copy of its text in the language you're using in the admin, ready to translate, the same way Zen Cart copies your product text.
- Nothing in your template or in Zen Cart is changed. Everything lives in `zc_plugins/ProductRecipes/`.

## How adding to the cart works

Only ingredients that are linked to an active product can be added. The plugin then hands the list to Zen Cart's own cart, so minimum and maximum quantities, quantity units and your stock rules all apply exactly as they do on a product page.

An ingredient is shown but **not** offered for the cart when:

- its product has options to choose (size, flavor...): it shows **Choose Options**, which opens the product page, because only the shopper can choose them;
- the product is call-for-price, can't be added to the cart, or your store hides prices from this visitor (for example until they log in);
- it's out of stock and your store doesn't allow checkout of out-of-stock items.

A product that's turned off shows as plain text. If you delete a product in **Catalog**, its ingredient lines stay in your recipes as plain text.

## Installing

1. Upload the `zc_plugins/ProductRecipes/` directory from the package to your store, so that it lands at `<store root>/zc_plugins/ProductRecipes/v1.0.0/`. Nothing goes anywhere else.
2. In your admin, go to **Modules → Plugin Manager**, select **Product Recipes** and click **Install**.
3. Go to **Extras → Recipes** and click **New Recipe**.

The storefront page is `index.php?main_page=recipes`. The plugin is switched on as soon as it's installed. Its settings are under **Configuration → Product Recipes**.

Once it's installed, a superuser can open this guide from the **ReadMe** button in Plugin Manager.

To give shoppers a way in:

- On Zen Cart 2.0.0 and later, a **Recipes** link is added to the Information sidebox automatically (a setting turns it off).
- On any version, turn on the **Recipes** sidebox in **Tools → Layout Boxes Controller** (it's listed as `product_recipes.php`). It shows your newest recipes and the cooking methods. Zen Cart 1.5.8 has no way for a plugin to add to the Information sidebox, so on 1.5.8 the sidebox is the built-in way in.
- Or link to `index.php?main_page=recipes` from an EZ-Page, a banner or your menu.

> If your store is itself a git checkout of Zen Cart, `zc_plugins/.gitignore` uses a deny-all rule with an allowlist; add `!ProductRecipes/` and `!ProductRecipes/**` to it or git will not see the plugin. This has no effect on the plugin working.

## Writing a recipe

**Extras → Recipes → New Recipe** opens the form.

- **Recipe name** is required (in at least one language). **Short introduction** is plain text and shows on the Recipes page and at the top of the recipe.
- **Show on the storefront** turns the recipe on or off. **Sort order** puts lower numbers first.
- **Servings**, **Prep time** and **Cook time** are shown when they're above 0.
- **Cooking methods**: tick every method that applies.
- **Picture**: upload a jpg, png, gif or webp. It's saved in `images/recipes/`.
- **Instructions** use your admin's HTML editor if you have one set (Configuration → My Store → HTML Editor). **Notes** are plain text.

### Ingredients

One row per ingredient:

| Column | What to put there |
|---|---|
| Sort | Leave blank to keep the rows in the order shown. |
| Amount | `2`, `1 1/2`, `1/2`, `0.75`, or blank. Shown as a kitchen fraction where it is one. |
| Unit | `cups`, `tsp`, `jar`, or blank. |
| Ingredient | What the shopper reads. Leave it blank on a linked row to use the product's own name. |
| Product ID or model | Links the row to a product. Type the product ID or the model number, or use **Find a product**. |
| Qty to add | How many of the product the quantity box starts at (default 1). |
| Optional | The row starts unticked, and the recipe says "(optional)". |
| Remove | Deletes the row when you save. |

To link a product without knowing its ID: type part of its name or model in **Find a product** and click **Find**. Your form is kept. Click the row's Product box, then **Use** beside the product you want. Need more rows? **Add 5 More Rows**.

After saving, each linked row shows which product it points to, and warns if the product is turned off or has options shoppers must choose.

**Copy** on the recipe list duplicates a recipe (turned off, with "(copy)" after its name) so you can make a variation quickly. **Delete** asks first. The picture file stays on the server.

## Cooking methods

**Extras → Recipes → Cooking Methods** lists the methods with how many recipes use each. Rename them, change their order, turn them off (hidden on the storefront, links kept), add a new one at the bottom, or delete one (recipes just lose that method). On a store with several languages, each method has a name per language.

## Settings

**Configuration → Product Recipes**:

| Setting | Default | What it does |
|---|---|---|
| Enable Product Recipes? | true | Master switch for the storefront. When false, the Recipes page sends visitors to the home page and no recipe links appear. Extras → Recipes keeps working. |
| Recipes per page | 12 | How many recipes the Recipes page lists before it starts a new page. |
| Show product prices? | true | Show each linked product's price. Your store's own price-visibility rules still apply. |
| Show stock? | true | Show In stock or Out of stock beside each linked product. |
| Allow adding ingredients to the cart? | true | The quantity boxes and Add to Cart buttons. When false, linked ingredients are still links. |
| Out-of-stock ingredients | Show | **Show** keeps the link and price; it can be added only if your store allows checkout of out-of-stock items. **Text Only** shows the ingredient as plain text. |
| Show the cooking-method filter? | true | The All / Microwave / Bake / No Bake links at the top of the Recipes page. |
| Add a Recipes link to the Information sidebox? | true | Zen Cart 2.0.0 and later. |
| Recipes sidebox: how many recipes | 3 | How many of the newest recipes the sidebox lists. |
| Thumbnail width (pixels) | 220 | The picture width on the Recipes page. |

## Customizing

Every storefront file can be overridden from your own template, the usual Zen Cart way. Copy the file from the plugin to the same place under your template and edit the copy:

| File in `zc_plugins/ProductRecipes/v1.0.0/catalog/includes/` | Copy to `includes/` |
|---|---|
| `templates/default/templates/tpl_recipes_default.php` | `templates/<your template>/templates/` |
| `templates/default/templates/tpl_modules_product_recipes_listing.php` | `templates/<your template>/templates/` |
| `templates/default/templates/tpl_modules_product_recipes_recipe.php` | `templates/<your template>/templates/` |
| `templates/default/sideboxes/tpl_product_recipes.php` | `templates/<your template>/sideboxes/` |
| `languages/english/lang.recipes.php` | `languages/english/<your template>/` (only the lines you change) |

The stylesheet, `product_recipes.css`, is loaded on the Recipes page only. Every element is inside `#productRecipes` and has a `productRecipes...` class, so your template's stylesheet can restyle anything. Add your CSS there rather than editing the plugin's file, which an upgrade replaces.

To put a paragraph at the top of the Recipes page, define `TEXT_RECIPES_PAGE_INTRO` in your copy of `lang.recipes.php`.

### Translating

Copy `catalog/includes/languages/english/` and `admin/includes/languages/english/` inside the plugin to a folder named for your language (for example `spanish/`) and translate the values. Anything not translated falls back to English, on every Zen Cart version.

### For developers

Product Recipes fires notifiers that another plugin can observe. The full list, with parameters, is in `docs/CUSTOMIZING.md` in the package. They cover the admin form, saving, copying and deleting, the index query and filter links, each recipe and ingredient line as it's built, adding to the cart, and three places in the recipe template.

## Upgrading

Upload the new version's directory beside the old one, so both the old directory and the new one are present under `zc_plugins/ProductRecipes/`, then in Plugin Manager select the plugin and click **Upgrade**. Your recipes, cooking methods and settings are kept: an upgrade refreshes the labels and help text of each setting but never its value, and never re-adds a cooking method you deleted. Delete the old version directory afterwards.

## Uninstalling

In Plugin Manager, select the plugin and click **Uninstall**. **That deletes every recipe, ingredient and cooking method**, the settings and the menu entries, so back up your database first if you might want them again. Pictures in `images/recipes/` are left on the server for you to delete or keep. Then delete `zc_plugins/ProductRecipes/`. No other file in your store was changed.

## Troubleshooting

### The Recipes page sends me to the home page

**Configuration → Product Recipes → Enable Product Recipes?** is false.

### There's no Recipes link anywhere

On Zen Cart 1.5.8 there's no automatic link: turn on the Recipes sidebox in **Tools → Layout Boxes Controller**. On 2.0.0 and later, check the Information sidebox setting, and that your template shows the Information sidebox.

### An ingredient has no Add to Cart button

See "How adding to the cart works" above. The usual reasons: the product has options (it shows Choose Options), it's out of stock, or the shopper isn't allowed to see prices yet. In the admin, the row's note says which product it's linked to and whether it's turned off or has options.

### "No product matches" when saving

The Product box takes a product ID (a number) or a model number exactly as it's stored. Use **Find a product** to look it up.

### The picture didn't upload

The admin message says why. The folder `images/recipes/` must be writable by the web server (the plugin creates it if it can). Only jpg, png, gif and webp are accepted.

## Support

Questions and problem reports go to the Zen Cart forum support thread (https://www.zen-cart.com/threads/207509#post-1348069). Please include your Zen Cart and PHP versions.

Source, issues and releases are on GitHub (https://github.com/dbltoe/Product_Recipes).

## Changelog

See `CHANGELOG.md`. Version 1.0.0 is the first release.

## License

Product Recipes is free software under the GNU General Public License, version 2 (https://www.gnu.org/licenses/old-licenses/gpl-2.0.html); the full text is in `LICENSE`. Copyright © 2026 My Zen Cart Host (dbltoe).
