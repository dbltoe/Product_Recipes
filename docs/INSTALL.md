# Installing Product Recipes

1. Upload `zc_plugins/ProductRecipes/` from the package so it lands at `<store root>/zc_plugins/ProductRecipes/v1.0.0/`. Nothing goes anywhere else.
2. Admin → **Modules → Plugin Manager** → **Product Recipes** → **Install**.
3. **Extras → Recipes → New Recipe**.

The storefront page is `index.php?main_page=recipes`. On Zen Cart 2.0.0 and later a Recipes link is added to the Information sidebox. On any version you can turn on the Recipes sidebox (`product_recipes.php`) in **Tools → Layout Boxes Controller**; on 1.5.8 that's the built-in way in.

**Upgrading:** upload the new version folder beside the old one, click **Upgrade** in Plugin Manager, then delete the old folder. Recipes, methods and settings are kept.

**Uninstalling** deletes every recipe, ingredient and cooking method, the settings and the menu entries. Back up first if you might want them. Pictures in `images/recipes/` stay on the server.

The full guide is `README.md`, also shown by the **ReadMe** button in Plugin Manager.
