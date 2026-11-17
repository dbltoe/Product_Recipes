<?php
/**
 * Product Recipes -- Plugin Manager installer.
 *
 * Limited to what exists on every supported release (1.5.8 -> 3.0.0): only
 * executeInstallerSql() and $this->dbConn are used. The convenience helpers
 * (addConfigurationKey() and friends) are 2.0.0+ and absent on 1.5.8.
 * executeUpgrade() takes an optional argument because 1.5.8 and 2.0.x call it
 * with none.
 *
 * Every step is idempotent, so an upgrade is a re-run of the install: tables are
 * CREATE TABLE IF NOT EXISTS, configuration keys are INSERT IGNORE followed by a
 * metadata UPDATE that never touches the store owner's value, and the cooking
 * methods are seeded only into an empty table.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    public const PLUGIN_KEY = 'ProductRecipes';

    public const CONFIG_GROUP_TITLE = 'Product Recipes';

    /** admin_pages.page_key values this plugin owns. */
    public const ADMIN_PAGE_KEYS = ['productRecipes', 'configProductRecipes'];

    /** Configuration keys from earlier versions that this one no longer reads. */
    public const RETIRED_KEYS = [];

    protected function executeInstall(): bool
    {
        $this->prDefineConstants();

        // Tables first and checked before anything else is written: nothing rolls back, so a
        // failed install must not leave configuration rows or menu entries behind.
        if ($this->prCreateTables() === false) {
            $this->errorContainer->addError(0, 'Product Recipes: table creation failed', false, TEXT_PRODUCT_RECIPES_INSTALL_ERROR_TABLES);
            return false;
        }

        $groupId = $this->prGetOrCreateConfigGroup();
        if ($groupId === 0) {
            $this->errorContainer->addError(0, 'Product Recipes: configuration group failed', false, TEXT_PRODUCT_RECIPES_INSTALL_ERROR_CONFIG);
            return false;
        }

        $this->prSeedMethods();
        $this->prRemoveRetiredKeys();
        $this->prAddConfigurationKeys($groupId);
        $this->prRegisterAdminPages($groupId);
        $this->prRefreshPluginDescription();
        $this->prLog('Product Recipes: installed/upgraded.');

        return !$this->errorContainer->hasErrors();
    }

    // 1.5.8 and 2.0.x call this with no argument; 2.1.0+ passes the old version.
    protected function executeUpgrade($oldVersion = null): bool
    {
        return $this->executeInstall();
    }

    protected function executeUninstall(): bool
    {
        $this->prDefineConstants();

        zen_deregister_admin_pages(self::ADMIN_PAGE_KEYS);

        foreach ($this->prGetConfigGroupIds() as $groupId) {
            $this->executeInstallerSql("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_group_id = " . (int)$groupId);
            $this->executeInstallerSql("DELETE FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_id = " . (int)$groupId);
        }
        $this->executeInstallerSql("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'PRODUCT\\_RECIPES\\_%'");

        foreach ($this->prTableNames() as $table) {
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . $table);
        }

        // The sidebox registers a layout_boxes row (one per template) the first time the Layout
        // Boxes Controller sees it; leaving them would point at a box file that's gone.
        if (defined('TABLE_LAYOUT_BOXES')) {
            $this->executeInstallerSql("DELETE FROM " . TABLE_LAYOUT_BOXES . " WHERE layout_box_name = 'product_recipes.php'");
        }

        $this->prRefreshPluginDescription();
        $this->prLog('Product Recipes: uninstalled. Recipe images in images/recipes/ were left in place.');

        return !$this->errorContainer->hasErrors();
    }

    /* ------------------------------------------------------------------ *
     * Tables
     * ------------------------------------------------------------------ */

    protected function prDefineConstants(): void
    {
        require dirname(__DIR__) . '/filenames.php';
        require dirname(__DIR__) . '/database_tables.php';
    }

    /**
     * @return string[]
     */
    protected function prTableNames(): array
    {
        return [
            TABLE_PRODUCT_RECIPES,
            TABLE_PRODUCT_RECIPES_DESCRIPTION,
            TABLE_PRODUCT_RECIPES_INGREDIENTS,
            TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION,
            TABLE_PRODUCT_RECIPES_METHODS,
            TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION,
            TABLE_PRODUCT_RECIPES_TO_METHODS,
        ];
    }

    protected function prCreateTables(): bool
    {
        $charset = (defined('DB_CHARSET') && preg_match('/^[a-z0-9_]+$/i', DB_CHARSET)) ? DB_CHARSET : 'utf8mb4';
        $tail = ") ENGINE=InnoDB DEFAULT CHARSET=" . $charset;

        $sql = [
            "CREATE TABLE IF NOT EXISTS " . TABLE_PRODUCT_RECIPES . " (
                recipes_id int(11) NOT NULL auto_increment,
                recipes_status tinyint(1) NOT NULL default 1,
                sort_order int(11) NOT NULL default 0,
                servings smallint(6) NOT NULL default 0,
                prep_minutes smallint(6) NOT NULL default 0,
                cook_minutes smallint(6) NOT NULL default 0,
                recipes_image varchar(255) NOT NULL default '',
                date_added datetime NOT NULL default '0001-01-01 00:00:00',
                last_modified datetime NULL default NULL,
                PRIMARY KEY (recipes_id),
                KEY idx_pr_status_sort (recipes_status, sort_order)
            " . $tail,
            "CREATE TABLE IF NOT EXISTS " . TABLE_PRODUCT_RECIPES_DESCRIPTION . " (
                recipes_id int(11) NOT NULL,
                language_id int(11) NOT NULL default 1,
                recipes_name varchar(255) NOT NULL default '',
                recipes_intro text,
                recipes_instructions mediumtext,
                recipes_notes text,
                PRIMARY KEY (recipes_id, language_id)
            " . $tail,
            "CREATE TABLE IF NOT EXISTS " . TABLE_PRODUCT_RECIPES_INGREDIENTS . " (
                ingredients_id int(11) NOT NULL auto_increment,
                recipes_id int(11) NOT NULL,
                sort_order int(11) NOT NULL default 0,
                products_id int(11) NOT NULL default 0,
                amount decimal(10,3) NULL default NULL,
                cart_qty decimal(10,3) NOT NULL default 1.000,
                optional tinyint(1) NOT NULL default 0,
                PRIMARY KEY (ingredients_id),
                KEY idx_pri_recipe (recipes_id, sort_order),
                KEY idx_pri_product (products_id)
            " . $tail,
            "CREATE TABLE IF NOT EXISTS " . TABLE_PRODUCT_RECIPES_INGREDIENTS_DESCRIPTION . " (
                ingredients_id int(11) NOT NULL,
                language_id int(11) NOT NULL default 1,
                ingredient_text varchar(255) NOT NULL default '',
                unit varchar(32) NOT NULL default '',
                PRIMARY KEY (ingredients_id, language_id)
            " . $tail,
            "CREATE TABLE IF NOT EXISTS " . TABLE_PRODUCT_RECIPES_METHODS . " (
                methods_id int(11) NOT NULL auto_increment,
                sort_order int(11) NOT NULL default 0,
                methods_status tinyint(1) NOT NULL default 1,
                PRIMARY KEY (methods_id)
            " . $tail,
            "CREATE TABLE IF NOT EXISTS " . TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION . " (
                methods_id int(11) NOT NULL,
                language_id int(11) NOT NULL default 1,
                methods_name varchar(64) NOT NULL default '',
                PRIMARY KEY (methods_id, language_id)
            " . $tail,
            "CREATE TABLE IF NOT EXISTS " . TABLE_PRODUCT_RECIPES_TO_METHODS . " (
                recipes_id int(11) NOT NULL,
                methods_id int(11) NOT NULL,
                PRIMARY KEY (recipes_id, methods_id),
                KEY idx_prm_method (methods_id)
            " . $tail,
        ];

        foreach ($sql as $statement) {
            $this->executeInstallerSql($statement);
        }

        foreach ($this->prTableNames() as $table) {
            $check = $this->dbConn->Execute("SHOW TABLES LIKE '" . $this->dbConn->prepare_input($table) . "'", false, false, 0, true);
            if ($check->EOF) {
                return false;
            }
        }
        return true;
    }

    /**
     * Microwave, Bake and No Bake, seeded only into an empty table so a store owner's renames,
     * additions and deletions survive every upgrade. The English name goes into every installed
     * language for the owner to translate.
     */
    protected function prSeedMethods(): void
    {
        $check = $this->dbConn->Execute("SELECT COUNT(*) AS total FROM " . TABLE_PRODUCT_RECIPES_METHODS, false, false, 0, true);
        if ((int)$check->fields['total'] > 0) {
            return;
        }

        $languageIds = [];
        $languages = $this->dbConn->Execute("SELECT languages_id FROM " . TABLE_LANGUAGES, false, false, 0, true);
        while (!$languages->EOF) {
            $languageIds[] = (int)$languages->fields['languages_id'];
            $languages->MoveNext();
        }
        if ($languageIds === []) {
            $languageIds = [1];
        }

        foreach (['Microwave' => 10, 'Bake' => 20, 'No Bake' => 30] as $name => $sort) {
            $this->executeInstallerSql(
                "INSERT INTO " . TABLE_PRODUCT_RECIPES_METHODS . " (sort_order, methods_status) VALUES (" . (int)$sort . ", 1)"
            );
            $id = $this->dbConn->Execute(
                "SELECT MAX(methods_id) AS id FROM " . TABLE_PRODUCT_RECIPES_METHODS,
                false, false, 0, true
            );
            $methodsId = (int)$id->fields['id'];
            foreach ($languageIds as $languageId) {
                $this->executeInstallerSql(
                    "INSERT IGNORE INTO " . TABLE_PRODUCT_RECIPES_METHODS_DESCRIPTION . " (methods_id, language_id, methods_name)
                     VALUES (" . $methodsId . ", " . $languageId . ", '" . $this->dbConn->prepare_input($name) . "')"
                );
            }
        }
    }

    /* ------------------------------------------------------------------ *
     * Configuration
     * ------------------------------------------------------------------ */

    protected function prGetConfigGroupId(): int
    {
        // The last argument bypasses the per-request query cache: this is re-read right after the
        // INSERT below.
        $check = $this->dbConn->Execute(
            "SELECT g.configuration_group_id
               FROM " . TABLE_CONFIGURATION_GROUP . " g
                    LEFT JOIN " . TABLE_CONFIGURATION . " c ON c.configuration_group_id = g.configuration_group_id
              WHERE g.configuration_group_title = '" . $this->dbConn->prepare_input(self::CONFIG_GROUP_TITLE) . "'
              GROUP BY g.configuration_group_id
              ORDER BY COUNT(c.configuration_key) DESC, g.configuration_group_id ASC
              LIMIT 1",
            false, false, 0, true
        );
        return $check->EOF ? 0 : (int)$check->fields['configuration_group_id'];
    }

    /**
     * @return int[]
     */
    protected function prGetConfigGroupIds(): array
    {
        $ids = [];
        $result = $this->dbConn->Execute(
            "SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . "
              WHERE configuration_group_title = '" . $this->dbConn->prepare_input(self::CONFIG_GROUP_TITLE) . "'",
            false, false, 0, true
        );
        while (!$result->EOF) {
            $ids[] = (int)$result->fields['configuration_group_id'];
            $result->MoveNext();
        }
        return $ids;
    }

    /**
     * Written with plugin SQL rather than core's addConfigurationGroup(): that helper doesn't
     * exist on 1.5.8, and on 2.1 through 3.0 it never finds an existing group and inserts a
     * duplicate on every upgrade.
     */
    protected function prGetOrCreateConfigGroup(): int
    {
        $groupId = $this->prGetConfigGroupId();
        if ($groupId > 0) {
            return $groupId;
        }

        $this->executeInstallerSql(
            "INSERT INTO " . TABLE_CONFIGURATION_GROUP . "
                (configuration_group_title, configuration_group_description, sort_order, visible)
             VALUES
                ('" . $this->dbConn->prepare_input(self::CONFIG_GROUP_TITLE) . "',
                 'Settings for the recipe pages: prices, stock, add to cart, cooking-method filter and sidebox.',
                 0, 1)"
        );
        $groupId = $this->prGetConfigGroupId();
        if ($groupId > 0) {
            // Zen Cart convention: a configuration group's sort_order matches its id.
            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION_GROUP . " SET sort_order = " . $groupId . " WHERE configuration_group_id = " . $groupId . " LIMIT 1"
            );
        }
        return $groupId;
    }

    protected function prRemoveRetiredKeys(): void
    {
        foreach (self::RETIRED_KEYS as $key) {
            $this->executeInstallerSql(
                "DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $this->dbConn->prepare_input($key) . "'"
            );
        }
    }

    /**
     * The settings, in the order Configuration > Product Recipes shows them.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prConfigurationKeys(): array
    {
        $yesNo = "zen_cfg_select_option(array('true', 'false'), ";

        return [
            [
                'key' => 'PRODUCT_RECIPES_STATUS',
                'title' => 'Enable Product Recipes?',
                'value' => 'true',
                'description' => 'Master switch for the storefront. When false, the Recipes page sends visitors to the home page and no recipe links or sidebox content appear. Extras > Recipes keeps working either way.',
                'sort_order' => 10,
                'set_function' => $yesNo,
            ],
            [
                'key' => 'PRODUCT_RECIPES_PER_PAGE',
                'title' => 'Recipes per page',
                'value' => '12',
                'description' => 'How many recipes the Recipes page lists before it starts a new page.',
                'sort_order' => 20,
                'set_function' => '',
            ],
            [
                'key' => 'PRODUCT_RECIPES_SHOW_PRICES',
                'title' => 'Show product prices?',
                'value' => 'true',
                'description' => 'Show each linked product\'s price beside its ingredient. Your store\'s own rules about who may see prices still apply.',
                'sort_order' => 30,
                'set_function' => $yesNo,
            ],
            [
                'key' => 'PRODUCT_RECIPES_SHOW_STOCK',
                'title' => 'Show stock?',
                'value' => 'true',
                'description' => 'Show In Stock or Out of Stock beside each linked product.',
                'sort_order' => 40,
                'set_function' => $yesNo,
            ],
            [
                'key' => 'PRODUCT_RECIPES_ADD_TO_CART',
                'title' => 'Allow adding ingredients to the cart?',
                'value' => 'true',
                'description' => 'Show a quantity box and an Add to Cart button on each linked ingredient, plus an "Add Checked to Cart" button for the whole list. Products with options to choose always link to their product page instead.',
                'sort_order' => 50,
                'set_function' => $yesNo,
            ],
            [
                'key' => 'PRODUCT_RECIPES_OUT_OF_STOCK',
                'title' => 'Out-of-stock ingredients',
                'value' => 'Show',
                'description' => '<b>Show</b>: the product link and price stay. It can be added to the cart only if your store allows checkout of out-of-stock items.<br><br><b>Text Only</b>: the ingredient shows as plain text, with no link, price or button.',
                'sort_order' => 60,
                'set_function' => "zen_cfg_select_option(array('Show', 'Text Only'), ",
            ],
            [
                'key' => 'PRODUCT_RECIPES_SHOW_METHOD_FILTER',
                'title' => 'Show the cooking-method filter?',
                'value' => 'true',
                'description' => 'Show links (All, Microwave, Bake, No Bake, and any methods you add) at the top of the Recipes page.',
                'sort_order' => 70,
                'set_function' => $yesNo,
            ],
            [
                'key' => 'PRODUCT_RECIPES_INFO_SIDEBOX_LINK',
                'title' => 'Add a Recipes link to the Information sidebox?',
                'value' => 'true',
                'description' => 'Adds a Recipes link to your template\'s Information sidebox. Zen Cart 2.0.0 and later only: Zen Cart 1.5.8 has no way for a plugin to add it, so use the Recipes sidebox there.',
                'sort_order' => 80,
                'set_function' => $yesNo,
            ],
            [
                'key' => 'PRODUCT_RECIPES_SIDEBOX_MAX',
                'title' => 'Recipes sidebox: how many recipes',
                'value' => '3',
                'description' => 'How many of the newest recipes the Recipes sidebox lists. Turn the sidebox on in Tools > Layout Boxes Controller.',
                'sort_order' => 90,
                'set_function' => '',
            ],
            [
                'key' => 'PRODUCT_RECIPES_IMAGE_WIDTH',
                'title' => 'Thumbnail width (pixels)',
                'value' => '220',
                'description' => 'Width of each recipe\'s picture on the Recipes page. The picture on a recipe\'s own page fits the page.',
                'sort_order' => 100,
                'set_function' => '',
            ],
        ];
    }

    protected function prAddConfigurationKeys(int $groupId): void
    {
        foreach ($this->prConfigurationKeys() as $key) {
            $setFunction = ($key['set_function'] === '') ? 'NULL' : "'" . $this->dbConn->prepare_input($key['set_function']) . "'";

            $this->executeInstallerSql(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description,
                     configuration_group_id, sort_order, date_added, use_function, set_function)
                 VALUES
                    ('" . $this->dbConn->prepare_input($key['title']) . "',
                     '" . $this->dbConn->prepare_input($key['key']) . "',
                     '" . $this->dbConn->prepare_input($key['value']) . "',
                     '" . $this->dbConn->prepare_input($key['description']) . "',
                     " . $groupId . ", " . (int)$key['sort_order'] . ", now(), NULL, " . $setFunction . ")"
            );

            // INSERT IGNORE leaves an existing row alone, which is right for the value (the store
            // owner's). The label, help text, order and input type belong to the plugin.
            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                    SET configuration_title = '" . $this->dbConn->prepare_input($key['title']) . "',
                        configuration_description = '" . $this->dbConn->prepare_input($key['description']) . "',
                        configuration_group_id = " . $groupId . ",
                        sort_order = " . (int)$key['sort_order'] . ",
                        set_function = " . $setFunction . "
                  WHERE configuration_key = '" . $this->dbConn->prepare_input($key['key']) . "'
                  LIMIT 1"
            );
        }
    }

    /* ------------------------------------------------------------------ *
     * Admin menu entries
     * ------------------------------------------------------------------ */

    /**
     * Extras > Recipes and Configuration > Product Recipes. zen_register_admin_page() is a plain
     * INSERT on a unique page_key, so deregister first or an upgrade fails on a duplicate key.
     */
    protected function prRegisterAdminPages(int $groupId): void
    {
        zen_deregister_admin_pages(self::ADMIN_PAGE_KEYS);
        zen_register_admin_page('productRecipes', 'BOX_EXTRAS_PRODUCT_RECIPES', 'FILENAME_PRODUCT_RECIPES', '', 'extras', 'Y');
        zen_register_admin_page('configProductRecipes', 'BOX_CONFIGURATION_PRODUCT_RECIPES', 'FILENAME_CONFIGURATION', 'gID=' . $groupId, 'configuration', 'Y', $groupId);
    }

    /**
     * Save the manifest's description again now that the Settings page exists (install, upgrade)
     * or is gone (uninstall). 1.5.8, 2.0 and 2.1 store the description only when Plugin Manager
     * first sees the plugin; 2.2 and later re-read the manifest anyway.
     */
    protected function prRefreshPluginDescription(): void
    {
        if (!defined('TABLE_PLUGIN_CONTROL')) {
            return;
        }
        $manifest = require dirname(__DIR__) . '/manifest.php';
        if (!is_array($manifest) || !isset($manifest['pluginDescription'])) {
            return;
        }
        $this->executeInstallerSql(
            "UPDATE " . TABLE_PLUGIN_CONTROL . "
                SET description = '" . $this->dbConn->prepare_input($manifest['pluginDescription']) . "'
              WHERE unique_key = '" . $this->dbConn->prepare_input(self::PLUGIN_KEY) . "'
              LIMIT 1"
        );
    }

    protected function prLog(string $message): void
    {
        if (function_exists('zen_record_admin_activity')) {
            zen_record_admin_activity($message, 'info');
        }
    }
}
