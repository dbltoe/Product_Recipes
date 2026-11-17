<?php
/**
 * Product Recipes -- storefront constants for Zen Cart 1.5.8 through 2.1, which don't load a
 * plugin's root filenames.php / database_tables.php (2.2.0 and later load both; the guarded
 * defines make the second load harmless).
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
require __DIR__ . '/../../../filenames.php';
require __DIR__ . '/../../../database_tables.php';
