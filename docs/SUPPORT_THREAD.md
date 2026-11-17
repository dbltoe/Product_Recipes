# Product Recipes - support thread

Opening post of the Zen Cart forum support thread (All Other Contributions/Addons), posted 2026-11-17:
https://www.zen-cart.com/threads/207509#post-1348069

Markdown, as posted. Not part of the release package.

---

**Product Recipes v1.0.0** adds a Recipes page to your store, where each ingredient can be a product you sell. Shoppers read the recipe and add what they need to the cart from the same page. It's free and GPL v2.

Download: https://github.com/dbltoe/Product_Recipes/releases/latest

**What shoppers see**

- A Recipes page listing your recipes with a picture, servings and times, filtered by cooking method.
- Each recipe has its ingredients, the steps and your notes. An ingredient linked to a product shows the product's current price and stock, links to its product page, and has its own Add to Cart button.
- **Add Checked to Cart:** shoppers tick the ingredients they need and add them all at once.
- A product with options to choose (size, flavor) shows **Choose Options** and opens its product page, because only the shopper can choose them.
- Every recipe prints cleanly; the cart buttons and filters drop out of the printout.

**How adding to the cart works**

The plugin hands the ingredients to Zen Cart's own cart, so minimums, maximums, quantity units and your stock rules apply exactly as they do on a product page. Products that are call-for-price, out of stock (when your store doesn't sell out-of-stock items), or whose prices this visitor can't see are shown but not offered for the cart.

**Writing recipes**

Everything is on **Extras > Recipes**: name, introduction, picture, servings, prep and cook times, the steps in your admin's HTML editor, notes, and the ingredient rows. Link a row by product ID or model, or use **Find a product** to look it up by name. Amounts can be written the way recipes write them: `1 1/2`, `1/2`, `0.75`. Ingredients that aren't products ("salt to taste") are plain text.

**Cooking methods** Microwave, Bake and No Bake are ready when you install. Rename them, add your own (Grill, Slow Cooker) or turn any off. A recipe can have more than one.

**Other things worth knowing**

- Several languages are supported, with one box per language for every text field.
- A Recipes sidebox (newest recipes and the cooking methods) can be turned on in **Tools > Layout Boxes Controller**. On Zen Cart 2.0.0 and later, a Recipes link is also added to the Information sidebox.
- Every storefront file can be overridden from your own template, and the plugin fires notifiers another plugin can use. The list is in `docs/CUSTOMIZING.md`.
- No core file and no template file is edited. Everything lives in `zc_plugins/ProductRecipes/`.

**What you need**

- Zen Cart 1.5.8 through 3.0.0, PHP 7.4 through 8.5.

**Getting started**

1. Copy `zc_plugins/ProductRecipes` into your store's `zc_plugins` folder.
2. Install it under **Modules > Plugin Manager**.
3. Go to **Extras > Recipes** and click **New Recipe**.

The storefront page is `index.php?main_page=recipes`. Settings are under **Configuration > Product Recipes**.

**Uninstalling**

Uninstall deletes every recipe, ingredient and cooking method, so back up your database first if you might want them again. Pictures in `images/recipes/` stay on the server.

The full guide is in the download's `README.md`, and once it's installed, the **ReadMe** button in Plugin Manager opens it for superusers.

Questions and problem reports are welcome here. Please include your Zen Cart and PHP versions.
