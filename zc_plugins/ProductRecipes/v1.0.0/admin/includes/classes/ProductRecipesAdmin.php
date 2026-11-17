<?php
/**
 * Product Recipes -- the admin page's reads and writes (Extras > Recipes).
 *
 * Plain class, required by admin/product_recipes.php through __DIR__ behind
 * class_exists('ProductRecipesAdmin', false). Request values are validated here and written
 * with zen_db_perform() or cast to int; nothing from a form reaches SQL unescaped.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
class ProductRecipesAdmin
{
    /** @var array<int, array<string, mixed>> zen_get_languages() */
    protected $languages;

    /** @var string[] messages for the page, filled by save() */
    public $errors = [];

    /** @var string[] */
    public $warnings = [];

    /**
     * @param array<int, array<string, mixed>> $languages
     */
    public function __construct(array $languages)
    {
        $this->languages = $languages;
    }

    /**
     * @return int[]
     */
    public function languageIds(): array
    {
        $ids = [];
        foreach ($this->languages as $language) {
            $ids[] = (int)$language['id'];
        }
        return $ids === [] ? [1] : $ids;
    }

    /** Cut a string to a byte-safe number of UTF-8 characters. */
    public static function cut(string $text, int $length): string
    {
        $text = trim($text);
        if (preg_match('/^.{0,' . $length . '}/us', $text, $m) === 1) {
            return $m[0];
        }
        return substr($text, 0, $length);
    }

    /* ------------------------------------------------------------------ *
     * Listing
     * ------------------------------------------------------------------ */

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function listRecipes(string $search, int $methodId, int $pageNumber, int $perPage): array
    {
        global $db;
        $languageId = ProductRecipesService::languageId();

        $from = " FROM " . TABLE_PRODUCT_RECIPES . " r
                  LEFT JOIN " . TABLE_PRODUCT_RECIPES_DESCRIPTION . " rd
                    ON rd.recipes_id = r.recipes_id AND rd.language_id = " . (int)$languageId;
        if ($methodId > 0) {
            $from .= " INNER JOIN " . TABLE_PRODUCT_RECIPES_TO_METHODS . " rm
                         ON rm.recipes_id = r.recipes_id AND rm.methods_id = " . (int)$methodId;
        }
        $where = " WHERE 1";
        $search = trim($search);
        if ($search !== '') {
            $like = zen_db_input(addcslashes($search, '%_\\'));
            $where .= " AND (rd.recipes_name LIKE '%" . $like . "%' OR r.recipes_id = " . (int)$search . ")";
        }

        $count = $db->Execute("SELECT COUNT(*) AS total" . $from . $where);
        $total = (int)$count->fields['total'];

        $offset = max(0, ($pageNumber - 1) * $perPage);
        $rows = [];
        $result = $db->Execute(
            "SELECT r.recipes_id, r.recipes_status, r.sort_order, rd.recipes_name,
                    (SELECT COUNT(*) FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " i WHERE i.recipes_id = r.recipes_id) AS ingredient_count,
                    (SELECT COUNT(*) FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " i WHERE i.recipes_id = r.recipes_id AND i.products_id > 0) AS linked_count"
            . $from . $where . "
              ORDER BY r.sort_order, rd.recipes_name, r.recipes_id
              LIMIT " . (int)$offset . ", " . (int)$perPage
        );
        while (!$result->EOF) {
            $rows[] = $result->fields;
            $result->MoveNext();
        }
        return ['rows' => $rows, 'total' => $total];
    }

    /* ------------------------------------------------------------------ *
     * Form data
     * ------------------------------------------------------------------ */

    /**
     * An empty form.
     *
     * @return array<string, mixed>
     */
    public function blankForm(): array
    {
        $empty = array_fill_keys($this->languageIds(), '');
        return [
            'recipes_id' => 0,
            'recipes_status' => 1,
            'sort_order' => 0,
            'servings' => 0,
            'prep_minutes' => 0,
            'cook_minutes' => 0,
            'recipes_image' => '',
            'names' => $empty,
            'intros' => $empty,
            'instructions' => $empty,
            'notes' => $empty,
            'methods' => [],
            'rows' => [],
        ];
    }

    /**
     * The form, filled from the database, or null if the recipe is gone.
     *
     * @return array<string, mixed>|null
     */
    public function formFromDatabase(int $recipesId): ?array
    {
        global $db;
        $recipe = $db->Execute("SELECT * FROM " . TABLE_PRODUCT_RECIPES . " WHERE recipes_id = " . (int)$recipesId . " LIMIT 1");
        if ($recipe->EOF) {
            return null;
        }
        $form = $this->blankForm();
        foreach (['recipes_id', 'recipes_status', 'sort_order', 'servings', 'prep_minutes', 'cook_minutes'] as $field) {
            $form[$field] = (int)$recipe->fields[$field];
        }
        $form['recipes_image'] = (string)$recipe->fields['recipes_image'];

        $descriptions = $db->Execute("SELECT * FROM " . TABLE_PRODUCT_RECIPES_DESCRIPTION . " WHERE recipes_id = " . (int)$recipesId);
        while (!$descriptions->EOF) {
            $lid = (int)$descriptions->fields['language_id'];
            $form['names'][$lid] = (string)$descriptions->fields['recipes_name'];
            $form['intros'][$lid] = (string)$descriptions->fields['recipes_intro'];
            $form['instructions'][$lid] = (string)$descriptions->fields['recipes_instructions'];
            $form['notes'][$lid] = (string)$descriptions->fields['recipes_notes'];
            $descriptions->MoveNext();
        }

        $methods = $db->Execute("SELECT methods_id FROM " . TABLE_PRODUCT_RECIPES_TO_METHODS . " WHERE recipes_id = " . (int)$recipesId);
        while (!$methods->EOF) {
            $form['methods'][] = (int)$methods->fields['methods_id'];
            $methods->MoveNext();
        }

        $rowsById = [];
        $ingredients = $db->Execute(
            "SELECT * FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " WHERE recipes_id = " . (int)$recipesId . " ORDER BY sort_order, ingredients_id"
        );
        while (!$ingredients->EOF) {
            $id = (int)$ingredients->fields['ingredients_id'];
            $rowsById[$id] = [
                'id' => $id,
                'amount' => ProductRecipesService::formatAmount($ingredients->fields['amount']),
                'unit' => array_fill_keys($this->languageIds(), ''),
                'text' => array_fill_keys($this->languageIds(), ''),
                'product' => ((int)$ingredients->fields['products_id'] > 0) ? (string)(int)$ingredients->fields['products_id'] : '',
                'cart_qty' => ProductRecipesService::formatAmount($ingredients->fields['cart_qty']),
                'optional' => (int)$ingredients->fields['optional'],
                'sort' => (int)$ingredients->fields['sort_order'],
                'remove' => 0,
            ];
            $ingredients->MoveNext();
        }
        if ($rowsById !== []) {
            $texts = $db->Execute(
                "SELECT * FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION . "
                  WHERE ingredients_id IN (" . implode(',', array_keys($rowsById)) . ")"
            );
            while (!$texts->EOF) {
                $id = (int)$texts->fields['ingredients_id'];
                $lid = (int)$texts->fields['language_id'];
                if (isset($rowsById[$id])) {
                    $rowsById[$id]['text'][$lid] = (string)$texts->fields['ingredient_text'];
                    $rowsById[$id]['unit'][$lid] = (string)$texts->fields['unit'];
                }
                $texts->MoveNext();
            }
        }
        $form['rows'] = array_values($rowsById);
        return $form;
    }

    /**
     * The form as the admin posted it (for Find, More Rows, or a failed save). Nothing is trusted
     * yet; save() validates again.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function formFromPost(array $post): array
    {
        $form = $this->blankForm();
        $form['recipes_id'] = (int)($post['recipes_id'] ?? 0);
        $form['recipes_status'] = empty($post['recipes_status']) ? 0 : 1;
        foreach (['sort_order', 'servings', 'prep_minutes', 'cook_minutes'] as $field) {
            $form[$field] = max(0, (int)($post[$field] ?? 0));
        }
        if ($form['recipes_id'] > 0) {
            // The picture is never taken from the form, only from the database.
            global $db;
            $image = $db->Execute("SELECT recipes_image FROM " . TABLE_PRODUCT_RECIPES . " WHERE recipes_id = " . $form['recipes_id'] . " LIMIT 1");
            $form['recipes_image'] = $image->EOF ? '' : (string)$image->fields['recipes_image'];
        }

        foreach ($this->languageIds() as $lid) {
            $form['names'][$lid] = self::postString($post, 'recipes_name', $lid);
            $form['intros'][$lid] = self::postString($post, 'recipes_intro', $lid);
            $form['instructions'][$lid] = self::postString($post, 'recipes_instructions', $lid);
            $form['notes'][$lid] = self::postString($post, 'recipes_notes', $lid);
        }

        $form['methods'] = [];
        if (isset($post['methods']) && is_array($post['methods'])) {
            foreach ($post['methods'] as $methodId) {
                if ((int)$methodId > 0) {
                    $form['methods'][] = (int)$methodId;
                }
            }
        }

        $form['rows'] = [];
        if (isset($post['ing']) && is_array($post['ing'])) {
            foreach ($post['ing'] as $index => $row) {
                if (!is_array($row) || !is_int($index) || $index < 0) {
                    continue;
                }
                $clean = [
                    'id' => (int)($row['id'] ?? 0),
                    'amount' => self::cut(is_string($row['amount'] ?? null) ? $row['amount'] : '', 20),
                    'unit' => [],
                    'text' => [],
                    'product' => self::cut(is_string($row['product'] ?? null) ? $row['product'] : '', 64),
                    'cart_qty' => self::cut(is_string($row['cart_qty'] ?? null) ? $row['cart_qty'] : '', 20),
                    'optional' => empty($row['optional']) ? 0 : 1,
                    'sort' => (int)($row['sort'] ?? 0),
                    'remove' => empty($row['remove']) ? 0 : 1,
                ];
                foreach ($this->languageIds() as $lid) {
                    $clean['text'][$lid] = self::cut(self::nestedString($row, 'text', $lid), 255);
                    $clean['unit'][$lid] = self::cut(self::nestedString($row, 'unit', $lid), 32);
                }
                // Keyed by the posted index (SAVE_END's ingredient_ids map promises it) and kept in
                // index order whatever order the fields arrived in.
                $form['rows'][$index] = $clean;
            }
            ksort($form['rows']);
        }
        return $form;
    }

    /**
     * @param array<string, mixed> $post
     */
    protected static function postString(array $post, string $field, int $languageId): string
    {
        if (!isset($post[$field]) || !is_array($post[$field]) || !isset($post[$field][$languageId])) {
            return '';
        }
        return is_string($post[$field][$languageId]) ? $post[$field][$languageId] : '';
    }

    /**
     * @param array<string, mixed> $row
     */
    protected static function nestedString(array $row, string $field, int $languageId): string
    {
        if (!isset($row[$field]) || !is_array($row[$field]) || !isset($row[$field][$languageId])) {
            return '';
        }
        return is_string($row[$field][$languageId]) ? $row[$field][$languageId] : '';
    }

    /** True when a row has nothing worth saving. */
    public static function rowIsBlank(array $row): bool
    {
        if (trim((string)$row['product']) !== '') {
            return false;
        }
        foreach ($row['text'] as $text) {
            if (trim((string)$text) !== '') {
                return false;
            }
        }
        return true;
    }

    /* ------------------------------------------------------------------ *
     * Save
     * ------------------------------------------------------------------ */

    /**
     * Insert or update a recipe from a posted form. Returns the recipe id, or 0 with $this->errors
     * set (nothing written in that case).
     *
     * @param array<string, mixed> $form from formFromPost()
     * @param array<string, mixed> $files $_FILES
     */
    public function save(array $form, array $files): int
    {
        global $db, $zco_notifier;
        $this->errors = [];
        $this->warnings = [];

        $hasName = false;
        foreach ($form['names'] as $name) {
            if (trim((string)$name) !== '') {
                $hasName = true;
                break;
            }
        }
        if (!$hasName) {
            $this->errors[] = ERROR_PR_NAME_REQUIRED;
            return 0;
        }

        $recipesId = (int)$form['recipes_id'];
        $isUpdate = false;
        if ($recipesId > 0) {
            $check = $db->Execute("SELECT recipes_id FROM " . TABLE_PRODUCT_RECIPES . " WHERE recipes_id = " . $recipesId . " LIMIT 1");
            if ($check->EOF) {
                $this->errors[] = ERROR_PR_NOT_FOUND;
                return 0;
            }
            $isUpdate = true;
        }

        $image = (string)$form['recipes_image'];
        if (!empty($_POST['remove_image'])) {
            $image = '';
        }
        $uploaded = $this->handleUpload($files);
        if ($uploaded !== null) {
            $image = $uploaded;
        }

        $data = [
            'recipes_status' => (int)$form['recipes_status'],
            'sort_order' => (int)$form['sort_order'],
            'servings' => min(32767, (int)$form['servings']),
            'prep_minutes' => min(32767, (int)$form['prep_minutes']),
            'cook_minutes' => min(32767, (int)$form['cook_minutes']),
            'recipes_image' => self::cut($image, 255),
        ];
        if ($isUpdate) {
            $data['last_modified'] = 'now()';
            zen_db_perform(TABLE_PRODUCT_RECIPES, $data, 'update', 'recipes_id = ' . $recipesId);
        } else {
            $data['date_added'] = 'now()';
            zen_db_perform(TABLE_PRODUCT_RECIPES, $data);
            $recipesId = (int)$db->Insert_ID();
        }

        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_DESCRIPTION . " WHERE recipes_id = " . $recipesId);
        foreach ($this->languageIds() as $lid) {
            zen_db_perform(TABLE_PRODUCT_RECIPES_DESCRIPTION, [
                'recipes_id' => $recipesId,
                'language_id' => $lid,
                'recipes_name' => self::cut((string)($form['names'][$lid] ?? ''), 255),
                'recipes_intro' => trim((string)($form['intros'][$lid] ?? '')),
                'recipes_instructions' => trim((string)($form['instructions'][$lid] ?? '')),
                'recipes_notes' => trim((string)($form['notes'][$lid] ?? '')),
            ]);
        }

        $this->saveMethods($recipesId, $form['methods']);
        $ingredientIds = $this->saveIngredients($recipesId, $form['rows']);

        $context = ['action' => $isUpdate ? 'update' : 'insert', 'ingredient_ids' => $ingredientIds];
        $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_ADMIN_SAVE_END', $recipesId, $context);

        return $recipesId;
    }

    /**
     * @param int[] $methodIds
     */
    protected function saveMethods(int $recipesId, array $methodIds): void
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_TO_METHODS . " WHERE recipes_id = " . $recipesId);
        $valid = array_keys(ProductRecipesService::getMethods(ProductRecipesService::languageId(), false));
        foreach (array_unique($methodIds) as $methodId) {
            if (in_array((int)$methodId, $valid, true)) {
                $db->Execute(
                    "INSERT IGNORE INTO " . TABLE_PRODUCT_RECIPES_TO_METHODS . " (recipes_id, methods_id)
                     VALUES (" . $recipesId . ", " . (int)$methodId . ")"
                );
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int|string, int> posted row index => ingredients_id
     */
    protected function saveIngredients(int $recipesId, array $rows): array
    {
        global $db;
        $map = [];

        $existing = [];
        $result = $db->Execute("SELECT ingredients_id FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " WHERE recipes_id = " . $recipesId);
        while (!$result->EOF) {
            $existing[(int)$result->fields['ingredients_id']] = true;
            $result->MoveNext();
        }

        $position = 0;
        foreach ($rows as $index => $row) {
            $id = (int)$row['id'];
            if ($id > 0 && !isset($existing[$id])) {
                $id = 0; // not this recipe's row: treat as new
            }
            if (!empty($row['remove']) || self::rowIsBlank($row)) {
                if ($id > 0) {
                    $this->deleteIngredient($id);
                }
                continue;
            }

            $position += 10;
            $productsId = 0;
            $ref = trim((string)$row['product']);
            if ($ref !== '') {
                $productsId = ProductRecipesService::resolveProductRef($ref);
                if ($productsId === 0) {
                    $this->warnings[] = sprintf(TEXT_PR_ING_PRODUCT_NOT_FOUND, zen_output_string_protected($ref));
                }
            }

            $amount = ProductRecipesService::parseAmount((string)$row['amount']);
            $cartQty = ProductRecipesService::parseAmount((string)$row['cart_qty']);
            $data = [
                'recipes_id' => $recipesId,
                'sort_order' => ((int)$row['sort'] !== 0) ? (int)$row['sort'] : $position,
                'products_id' => $productsId,
                'amount' => (is_float($amount) && $amount > 0) ? round($amount, 3) : 'null',
                'cart_qty' => (is_float($cartQty) && $cartQty > 0) ? round($cartQty, 3) : 1,
                'optional' => empty($row['optional']) ? 0 : 1,
            ];
            if ($id > 0) {
                zen_db_perform(TABLE_PRODUCT_RECIPES_INGREDIENTS, $data, 'update', 'ingredients_id = ' . $id);
            } else {
                zen_db_perform(TABLE_PRODUCT_RECIPES_INGREDIENTS, $data);
                $id = (int)$db->Insert_ID();
            }

            $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION . " WHERE ingredients_id = " . $id);
            foreach ($this->languageIds() as $lid) {
                zen_db_perform(TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION, [
                    'ingredients_id' => $id,
                    'language_id' => $lid,
                    'ingredient_text' => self::cut((string)($row['text'][$lid] ?? ''), 255),
                    'unit' => self::cut((string)($row['unit'][$lid] ?? ''), 32),
                ]);
            }
            $map[$index] = $id;
        }
        return $map;
    }

    protected function deleteIngredient(int $ingredientsId): void
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION . " WHERE ingredients_id = " . $ingredientsId);
        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " WHERE ingredients_id = " . $ingredientsId);
    }

    /**
     * Save an uploaded picture to images/recipes/. Returns its path relative to images/, or null
     * when nothing was uploaded or the upload failed (an error is recorded then).
     *
     * @param array<string, mixed> $files
     */
    protected function handleUpload(array $files): ?string
    {
        if (empty($files['recipes_image_upload']['name']) || !is_string($files['recipes_image_upload']['name'])) {
            return null;
        }
        $dir = DIR_FS_CATALOG_IMAGES . 'recipes/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            $this->warnings[] = ERROR_PR_IMAGE_DIR;
            return null;
        }
        if (!class_exists('upload')) {
            // Core's admin autoloads it on every release; this is only a safety net. In the admin
            // DIR_WS_CLASSES is the admin's own folder, hence the literal path.
            require_once DIR_FS_CATALOG . 'includes/classes/upload.php';
        }
        $upload = new upload('recipes_image_upload');
        $upload->set_extensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $upload->set_destination($dir);
        if ($upload->parse() && $upload->save()) {
            return 'recipes/' . $upload->filename;
        }
        $this->warnings[] = ERROR_PR_IMAGE_UPLOAD;
        return null;
    }

    /* ------------------------------------------------------------------ *
     * Status, copy, delete
     * ------------------------------------------------------------------ */

    public function toggleStatus(int $recipesId): void
    {
        global $db;
        $db->Execute(
            "UPDATE " . TABLE_PRODUCT_RECIPES . "
                SET recipes_status = 1 - recipes_status, last_modified = now()
              WHERE recipes_id = " . (int)$recipesId
        );
    }

    /**
     * Duplicate a recipe, its descriptions, ingredients and methods. The copy starts inactive.
     * Returns the new id, or 0.
     */
    public function copy(int $recipesId): int
    {
        global $db, $zco_notifier;
        $form = $this->formFromDatabase($recipesId);
        if ($form === null) {
            return 0;
        }

        $source = $db->Execute("SELECT * FROM " . TABLE_PRODUCT_RECIPES . " WHERE recipes_id = " . (int)$recipesId);
        zen_db_perform(TABLE_PRODUCT_RECIPES, [
            'recipes_status' => 0,
            'sort_order' => (int)$source->fields['sort_order'],
            'servings' => (int)$source->fields['servings'],
            'prep_minutes' => (int)$source->fields['prep_minutes'],
            'cook_minutes' => (int)$source->fields['cook_minutes'],
            'recipes_image' => (string)$source->fields['recipes_image'],
            'date_added' => 'now()',
        ]);
        $newId = (int)$db->Insert_ID();

        $descriptions = $db->Execute("SELECT * FROM " . TABLE_PRODUCT_RECIPES_DESCRIPTION . " WHERE recipes_id = " . (int)$recipesId);
        while (!$descriptions->EOF) {
            $name = (string)$descriptions->fields['recipes_name'];
            zen_db_perform(TABLE_PRODUCT_RECIPES_DESCRIPTION, [
                'recipes_id' => $newId,
                'language_id' => (int)$descriptions->fields['language_id'],
                'recipes_name' => self::cut($name === '' ? '' : $name . TEXT_PR_COPY_SUFFIX, 255),
                'recipes_intro' => (string)$descriptions->fields['recipes_intro'],
                'recipes_instructions' => (string)$descriptions->fields['recipes_instructions'],
                'recipes_notes' => (string)$descriptions->fields['recipes_notes'],
            ]);
            $descriptions->MoveNext();
        }

        $db->Execute(
            "INSERT INTO " . TABLE_PRODUCT_RECIPES_TO_METHODS . " (recipes_id, methods_id)
             SELECT " . $newId . ", methods_id FROM " . TABLE_PRODUCT_RECIPES_TO_METHODS . " WHERE recipes_id = " . (int)$recipesId
        );

        $map = [];
        $ingredients = $db->Execute("SELECT * FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " WHERE recipes_id = " . (int)$recipesId);
        while (!$ingredients->EOF) {
            $oldIngredient = (int)$ingredients->fields['ingredients_id'];
            zen_db_perform(TABLE_PRODUCT_RECIPES_INGREDIENTS, [
                'recipes_id' => $newId,
                'sort_order' => (int)$ingredients->fields['sort_order'],
                'products_id' => (int)$ingredients->fields['products_id'],
                'amount' => ($ingredients->fields['amount'] === null) ? 'null' : (float)$ingredients->fields['amount'],
                'cart_qty' => (float)$ingredients->fields['cart_qty'],
                'optional' => (int)$ingredients->fields['optional'],
            ]);
            $newIngredient = (int)$db->Insert_ID();
            $map[$oldIngredient] = $newIngredient;
            $db->Execute(
                "INSERT INTO " . TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION . " (ingredients_id, language_id, ingredient_text, unit)
                 SELECT " . $newIngredient . ", language_id, ingredient_text, unit
                   FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION . " WHERE ingredients_id = " . $oldIngredient
            );
            $ingredients->MoveNext();
        }

        $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_ADMIN_COPY', $recipesId, $newId, $map);
        return $newId;
    }

    public function delete(int $recipesId): void
    {
        global $db, $zco_notifier;
        $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_ADMIN_DELETE', $recipesId);

        $ids = [];
        $result = $db->Execute("SELECT ingredients_id FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " WHERE recipes_id = " . (int)$recipesId);
        while (!$result->EOF) {
            $ids[] = (int)$result->fields['ingredients_id'];
            $result->MoveNext();
        }
        if ($ids !== []) {
            $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION . " WHERE ingredients_id IN (" . implode(',', $ids) . ")");
        }
        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " WHERE recipes_id = " . (int)$recipesId);
        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_TO_METHODS . " WHERE recipes_id = " . (int)$recipesId);
        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_DESCRIPTION . " WHERE recipes_id = " . (int)$recipesId);
        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES . " WHERE recipes_id = " . (int)$recipesId);
    }

    /* ------------------------------------------------------------------ *
     * Cooking methods
     * ------------------------------------------------------------------ */

    /**
     * Methods with every language's name and a recipe count, for the Cooking Methods screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function methodsForEditing(): array
    {
        global $db;
        $methods = [];
        $result = $db->Execute(
            "SELECT m.methods_id, m.sort_order, m.methods_status,
                    (SELECT COUNT(*) FROM " . TABLE_PRODUCT_RECIPES_TO_METHODS . " rm WHERE rm.methods_id = m.methods_id) AS recipe_count
               FROM " . TABLE_PRODUCT_RECIPES_METHODS . " m
              ORDER BY m.sort_order, m.methods_id"
        );
        while (!$result->EOF) {
            $id = (int)$result->fields['methods_id'];
            $methods[$id] = [
                'methods_id' => $id,
                'sort_order' => (int)$result->fields['sort_order'],
                'methods_status' => (int)$result->fields['methods_status'],
                'recipe_count' => (int)$result->fields['recipe_count'],
                'names' => array_fill_keys($this->languageIds(), ''),
            ];
            $result->MoveNext();
        }
        if ($methods !== []) {
            $names = $db->Execute(
                "SELECT methods_id, language_id, methods_name FROM " . TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION . "
                  WHERE methods_id IN (" . implode(',', array_keys($methods)) . ")"
            );
            while (!$names->EOF) {
                $id = (int)$names->fields['methods_id'];
                if (isset($methods[$id])) {
                    $methods[$id]['names'][(int)$names->fields['language_id']] = (string)$names->fields['methods_name'];
                }
                $names->MoveNext();
            }
        }
        return $methods;
    }

    /**
     * Save renames, sort, on/off for existing methods, and add a new one if a name was typed.
     *
     * @param array<string, mixed> $post
     */
    public function saveMethodList(array $post): void
    {
        global $db;
        $existing = $this->methodsForEditing();
        $names = (isset($post['method_name']) && is_array($post['method_name'])) ? $post['method_name'] : [];
        $sorts = (isset($post['method_sort']) && is_array($post['method_sort'])) ? $post['method_sort'] : [];
        $statuses = (isset($post['method_status']) && is_array($post['method_status'])) ? $post['method_status'] : [];

        foreach ($existing as $id => $method) {
            zen_db_perform(TABLE_PRODUCT_RECIPES_METHODS, [
                'sort_order' => (int)($sorts[$id] ?? $method['sort_order']),
                'methods_status' => empty($statuses[$id]) ? 0 : 1,
            ], 'update', 'methods_id = ' . (int)$id);
            foreach ($this->languageIds() as $lid) {
                $name = (isset($names[$id]) && is_array($names[$id]) && is_string($names[$id][$lid] ?? null))
                    ? self::cut($names[$id][$lid], 64) : $method['names'][$lid];
                $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION . " WHERE methods_id = " . (int)$id . " AND language_id = " . (int)$lid);
                zen_db_perform(TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION, [
                    'methods_id' => (int)$id,
                    'language_id' => (int)$lid,
                    'methods_name' => $name,
                ]);
            }
        }

        $newNames = (isset($post['new_method_name']) && is_array($post['new_method_name'])) ? $post['new_method_name'] : [];
        $hasNew = false;
        foreach ($newNames as $name) {
            if (is_string($name) && trim($name) !== '') {
                $hasNew = true;
                break;
            }
        }
        if ($hasNew) {
            zen_db_perform(TABLE_PRODUCT_RECIPES_METHODS, [
                'sort_order' => (int)($post['new_method_sort'] ?? 0),
                'methods_status' => 1,
            ]);
            $newId = (int)$db->Insert_ID();
            $first = '';
            foreach ($newNames as $name) {
                if (is_string($name) && trim($name) !== '') {
                    $first = self::cut($name, 64);
                    break;
                }
            }
            foreach ($this->languageIds() as $lid) {
                $name = (is_string($newNames[$lid] ?? null) && trim($newNames[$lid]) !== '') ? self::cut($newNames[$lid], 64) : $first;
                zen_db_perform(TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION, [
                    'methods_id' => $newId,
                    'language_id' => (int)$lid,
                    'methods_name' => $name,
                ]);
            }
        }
    }

    public function deleteMethod(int $methodsId): void
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_TO_METHODS . " WHERE methods_id = " . (int)$methodsId);
        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION . " WHERE methods_id = " . (int)$methodsId);
        $db->Execute("DELETE FROM " . TABLE_PRODUCT_RECIPES_METHODS . " WHERE methods_id = " . (int)$methodsId);
    }
}
