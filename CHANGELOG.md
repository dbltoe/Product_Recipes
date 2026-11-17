# Changelog

## 1.0.0 (unreleased)

First release.

- Recipes page (`index.php?main_page=recipes`) with a paged index, a cooking-method filter, and one page per recipe.
- Ingredients can link to products: live price and stock, a link to the product page, per-line Add to Cart and "Add Checked to Cart". Adding goes through Zen Cart's own cart, so quantity rules and stock rules apply. Products with options link to their product page instead.
- Cooking methods: Microwave, Bake and No Bake to start with; rename, add, turn off or delete them in Extras → Recipes → Cooking Methods. A recipe can have several.
- Extras → Recipes: list, search, filter, add, edit, copy, delete, turn on and off. No-AJAX product finder. Kitchen-fraction amounts (`1 1/2`). Picture upload to `images/recipes/`. Instructions in the admin's HTML editor. One box per language for every text; a language added later gets a copy of the text to translate.
- Recipes sidebox and, on Zen Cart 2.0.0 and later, a link in the Information sidebox.
- Page title and description for each recipe. Print-friendly recipe pages.
- Notifiers for other plugins: admin form and save, copy, delete, index query, filter links, recipe and ingredient lines, add to cart, three template slots.
- Zen Cart 1.5.8 through 3.0.0, PHP 7.4 through 8.5. Encapsulated: no core or template file changed.
