<?php
/**
 * Product Recipes -- admin request sanitizer rules, scoped to the Extras > Recipes page.
 *
 * Every admin request runs through AdminRequestSanitizer; a field nobody registered gets the
 * strict default (quotes and ampersands stripped or escaped), so a recipe called "Mom's Chili &
 * Cornbread" would be saved mangled. Core's own types are used, matching core's own fields:
 * PRODUCT_NAME_DEEP_REGEX for the recipe name (what core gives products_name: cuts script and
 * event handlers), PRODUCT_DESC_REGEX for the intro, instructions and notes (what core gives
 * products_description: HTML allowed), WORDS_AND_SYMBOLS_REGEX for search boxes, and
 * NULL_ACTION for the nested ingredient and method arrays, which the page validates and escapes
 * itself (the regex types walk only one level). The class is loaded before plugin extra_datafiles
 * on every release 1.5.8 -> 3.0.0.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

if (class_exists('AdminRequestSanitizer', false)) {
    $prPages = ['product_recipes'];
    AdminRequestSanitizer::getInstance()->addComplexSanitization([
        'recipes_name' => ['sanitizerType' => 'PRODUCT_NAME_DEEP_REGEX', 'method' => 'post', 'pages' => $prPages],
        'recipes_intro' => ['sanitizerType' => 'PRODUCT_DESC_REGEX', 'method' => 'post', 'pages' => $prPages],
        'recipes_instructions' => ['sanitizerType' => 'PRODUCT_DESC_REGEX', 'method' => 'post', 'pages' => $prPages],
        'recipes_notes' => ['sanitizerType' => 'PRODUCT_DESC_REGEX', 'method' => 'post', 'pages' => $prPages],
        'q' => ['sanitizerType' => 'WORDS_AND_SYMBOLS_REGEX', 'method' => 'get', 'pages' => $prPages],
        'rp_find_q' => ['sanitizerType' => 'WORDS_AND_SYMBOLS_REGEX', 'method' => 'post', 'pages' => $prPages],
        'ing' => ['sanitizerType' => 'NULL_ACTION', 'method' => 'post', 'pages' => $prPages],
        'method_name' => ['sanitizerType' => 'NULL_ACTION', 'method' => 'post', 'pages' => $prPages],
        'new_method_name' => ['sanitizerType' => 'NULL_ACTION', 'method' => 'post', 'pages' => $prPages],
    ]);
    unset($prPages);
}
