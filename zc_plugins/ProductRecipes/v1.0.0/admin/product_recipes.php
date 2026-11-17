<?php
/**
 * Product Recipes -- Extras > Recipes (admin/index.php?cmd=product_recipes).
 *
 * Every change is a POST, which admin application_top checks for the securityToken. Variables
 * the page body reads after header.php are prefixed $pr: core's header navigation loops with
 * $page and $pages in global scope and would overwrite them.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */

// Reached only as admin/index.php?cmd=product_recipes, which defines IS_ADMIN_FLAG first. A
// direct request (where zc_plugins/.htaccess isn't honored) gets a bare 404 instead of a fatal
// that would print the server path.
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    http_response_code(404);
    exit;
}

require 'includes/application_top.php';

if (!class_exists('ProductRecipesService', false)) {
    require_once __DIR__ . '/../catalog/includes/classes/ProductRecipesService.php';
}
if (!class_exists('ProductRecipesAdmin', false)) {
    require_once __DIR__ . '/includes/classes/ProductRecipesAdmin.php';
}
// 1.5.8 and 2.0.x have no English fallback for plugin language files.
ProductRecipesService::defineFallbacks(__DIR__ . '/includes/languages/english/lang.product_recipes.php');
ProductRecipesService::defineFallbacks(__DIR__ . '/includes/languages/english/extra_definitions/lang.product_recipes.php');

$prLanguages = zen_get_languages();
$prAdmin = new ProductRecipesAdmin($prLanguages);
$prMultiLanguage = count($prLanguages) > 1;
$prAction = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';
$prRecipeId = isset($_GET['rID']) ? (int)$_GET['rID'] : 0;
$prSearch = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$prMethodFilter = isset($_GET['m']) ? (int)$_GET['m'] : 0;
$prPageNumber = isset($_GET['rp_page']) ? max(1, (int)$_GET['rp_page']) : 1;
$prPerPage = 25;
$prCharset = defined('CHARSET') ? CHARSET : 'UTF-8';

/** HTML-escape. */
$prH = static function ($text) use ($prCharset) {
    return htmlspecialchars((string)$text, ENT_QUOTES, $prCharset);
};

/** The list's own parameters, kept across actions. */
$prListParams = static function () use ($prSearch, $prMethodFilter, $prPageNumber) {
    $params = [];
    if ($prSearch !== '') {
        $params[] = 'q=' . urlencode($prSearch);
    }
    if ($prMethodFilter > 0) {
        $params[] = 'm=' . $prMethodFilter;
    }
    if ($prPageNumber > 1) {
        $params[] = 'rp_page=' . $prPageNumber;
    }
    return implode('&', $params);
};

/** An admin link to this page (already &amp;-joined, ready to echo). */
$prUrl = static function ($params = '') {
    return zen_href_link(FILENAME_PRODUCT_RECIPES, $params);
};

/** zen_redirect() doesn't decode &amp;, and admin zen_href_link() encodes it. */
$prRedirect = static function ($params = '') {
    zen_redirect(str_replace('&amp;', '&', zen_href_link(FILENAME_PRODUCT_RECIPES, $params)));
};

$prLanguageLabel = static function (array $language) use ($prMultiLanguage, $prH) {
    return $prMultiLanguage ? ' <span class="pr-lang">' . $prH($language['name']) . '</span>' : '';
};

/* ---------------------------------------------------------------------- *
 * Requests that change something (POST only)
 * ---------------------------------------------------------------------- */
$prForm = null;
$prFindResults = null;
$prMoreRows = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($prAction) {
        case 'save':
            $prForm = $prAdmin->formFromPost($_POST);
            if (isset($_POST['rp_find'])) {
                $prFindResults = ProductRecipesService::findProducts(
                    is_string($_POST['rp_find_q'] ?? null) ? $_POST['rp_find_q'] : '',
                    ProductRecipesService::languageId()
                );
                $prAction = ($prForm['recipes_id'] > 0) ? 'edit' : 'new';
                break;
            }
            if (isset($_POST['rp_more'])) {
                $prMoreRows = true;
                $prAction = ($prForm['recipes_id'] > 0) ? 'edit' : 'new';
                break;
            }
            $prSavedId = $prAdmin->save($prForm, $_FILES);
            if ($prSavedId > 0) {
                foreach ($prAdmin->warnings as $prWarning) {
                    $messageStack->add_session($prWarning, 'warning');
                }
                $messageStack->add_session(SUCCESS_PR_SAVED, 'success');
                $prRedirect(isset($_POST['rp_save_stay']) ? 'action=edit&rID=' . $prSavedId : $prListParams());
            }
            foreach ($prAdmin->errors as $prError) {
                $messageStack->add($prError, 'error');
            }
            $prAction = ($prForm['recipes_id'] > 0) ? 'edit' : 'new';
            break;

        case 'status':
            if ($prRecipeId > 0) {
                $prAdmin->toggleStatus($prRecipeId);
                $messageStack->add_session(SUCCESS_PR_STATUS, 'success');
            }
            $prRedirect($prListParams());
            break;

        case 'copy':
            $prNewId = $prAdmin->copy($prRecipeId);
            if ($prNewId > 0) {
                $messageStack->add_session(SUCCESS_PR_COPIED, 'success');
                $prRedirect('action=edit&rID=' . $prNewId);
            }
            $messageStack->add_session(ERROR_PR_NOT_FOUND, 'error');
            $prRedirect($prListParams());
            break;

        case 'delete':
            if ($prRecipeId > 0) {
                $prAdmin->delete($prRecipeId);
                $messageStack->add_session(SUCCESS_PR_DELETED, 'success');
            }
            $prRedirect($prListParams());
            break;

        case 'save_methods':
            $prAdmin->saveMethodList($_POST);
            $messageStack->add_session(SUCCESS_PR_METHODS_SAVED, 'success');
            $prRedirect('action=methods');
            break;

        case 'delete_method':
            $prMethodId = isset($_GET['mID']) ? (int)$_GET['mID'] : 0;
            if ($prMethodId > 0) {
                $prAdmin->deleteMethod($prMethodId);
                $messageStack->add_session(SUCCESS_PR_METHOD_DELETED, 'success');
            }
            $prRedirect('action=methods');
            break;

        default:
            $prRedirect($prListParams());
    }
}

/* ---------------------------------------------------------------------- *
 * What to show
 * ---------------------------------------------------------------------- */
$prView = 'list';
$prHeading = HEADING_TITLE_PRODUCT_RECIPES;
$prDeleteName = '';

if ($prAction === 'new' || $prAction === 'edit') {
    if ($prForm === null) {
        $prForm = ($prAction === 'edit') ? $prAdmin->formFromDatabase($prRecipeId) : $prAdmin->blankForm();
        if ($prForm === null) {
            $messageStack->add_session(ERROR_PR_NOT_FOUND, 'error');
            $prRedirect($prListParams());
        }
    }
    $prView = 'form';
    $prHeading = ($prForm['recipes_id'] > 0) ? HEADING_TITLE_PRODUCT_RECIPES_EDIT : HEADING_TITLE_PRODUCT_RECIPES_NEW;
} elseif ($prAction === 'delete') {
    $prDeleteForm = $prAdmin->formFromDatabase($prRecipeId);
    if ($prDeleteForm === null) {
        $messageStack->add_session(ERROR_PR_NOT_FOUND, 'error');
        $prRedirect($prListParams());
    }
    $prDeleteName = (string)($prDeleteForm['names'][ProductRecipesService::languageId()] ?? '');
    if ($prDeleteName === '') {
        $prDeleteName = '#' . $prRecipeId;
    }
    $prView = 'delete';
    $prHeading = HEADING_TITLE_PRODUCT_RECIPES_DELETE;
} elseif ($prAction === 'methods') {
    $prView = 'methods';
    $prHeading = HEADING_TITLE_PRODUCT_RECIPES_METHODS;
}

$prAllMethods = ProductRecipesService::getMethods(ProductRecipesService::languageId(), false);
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
<?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
<?php if ($prView === 'form' && isset($editor_handler) && $editor_handler !== '') { include $editor_handler; } ?>
<style>
/* Admin stylesheet.css sets form{display:inline} on every release; boxed forms need block. */
#prPage form.pr-block { display: block; }
#prPage .pr-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 10px 0 14px; }
#prPage .pr-panel { display: block; background: #f7f7f7; border: 1px solid #ddd; border-radius: 4px; padding: 12px 14px; margin: 0 0 16px; }
#prPage .pr-panel h2 { font-size: 1.15em; margin: 0 0 10px; }
#prPage .pr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px 16px; }
#prPage .pr-grid label, #prPage .pr-field label { display: block; font-weight: bold; margin-bottom: 3px; }
#prPage .pr-field { margin-bottom: 10px; }
#prPage .pr-lang { font-weight: normal; color: #555; font-size: .9em; }
#prPage .pr-help { color: #444; margin: 0 0 8px; }
#prPage .pr-on, #prPage .pr-off { display: inline-block; min-width: 3.2em; padding: 2px 6px; border-radius: 3px; border: 0; color: #fff; text-align: center; cursor: pointer; }
#prPage .pr-on { background: #2e7d32; }
#prPage .pr-off { background: #8a1c1c; }
#prPage table.pr-table { width: 100%; border-collapse: collapse; }
#prPage table.pr-table th, #prPage table.pr-table td { padding: 5px 6px; border-bottom: 1px solid #e3e3e3; vertical-align: top; text-align: left; }
#prPage table.pr-table th { background: #eee; }
#prPage .pr-ing input[type=text] { width: 100%; min-width: 4em; }
/* input.pr-narrow outranks the input[type=text] rule above; the freed width goes to the ingredient
   and product columns. */
#prPage .pr-ing input.pr-narrow { width: 5em; }
#prPage .pr-ing th:nth-child(4) { width: 34%; }
#prPage table.pr-table tr.pr-ing-extra td { border-bottom: 2px solid #d6d6d6; padding-top: 0; }
#prPage .pr-note { display: block; font-size: .9em; color: #555; margin-top: 2px; }
#prPage .pr-warn { color: #8a1c1c; }
#prPage .pr-actions form { display: inline; }
#prPage .pr-badge { display: inline-block; background: #e8eef7; border: 1px solid #c9d6ea; border-radius: 3px; padding: 0 5px; margin: 0 3px 3px 0; font-size: .9em; }
#prPage .pr-thumb { max-width: 160px; height: auto; display: block; margin-bottom: 6px; }
#prPage textarea { width: 100%; }
</style>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="container-fluid" id="prPage">
<h1><?php echo $prH($prHeading); ?></h1>
<?php
/* ====================================================================== *
 * List
 * ====================================================================== */
if ($prView === 'list') {
    $prList = $prAdmin->listRecipes($prSearch, $prMethodFilter, $prPageNumber, $prPerPage);
    $prRecipeMethods = ProductRecipesService::getMethodsForRecipes(
        array_map(static function ($row) {
            return (int)$row['recipes_id'];
        }, $prList['rows']),
        ProductRecipesService::languageId(),
        false
    );
    ?>
    <p class="pr-help"><?php echo $prH(TEXT_PR_INTRO_LIST); ?></p>
    <div class="pr-toolbar">
        <a class="btn btn-primary" href="<?php echo $prUrl('action=new'); ?>"><?php echo $prH(TEXT_PR_BUTTON_NEW); ?></a>
        <a class="btn btn-default" href="<?php echo $prUrl('action=methods'); ?>"><?php echo $prH(TEXT_PR_BUTTON_METHODS); ?></a>
        <?php echo zen_draw_form('prSearch', FILENAME_PRODUCT_RECIPES, '', 'get'); ?>
            <label for="prQ"><?php echo $prH(TEXT_PR_SEARCH_LABEL); ?></label>
            <input type="text" id="prQ" name="q" value="<?php echo $prH($prSearch); ?>">
            <label for="prM"><?php echo $prH(TEXT_PR_FILTER_METHOD); ?></label>
            <select id="prM" name="m">
                <option value="0"><?php echo $prH(TEXT_PR_FILTER_ALL); ?></option>
                <?php foreach ($prAllMethods as $prMethod) { ?>
                <option value="<?php echo (int)$prMethod['methods_id']; ?>"<?php echo ($prMethodFilter === (int)$prMethod['methods_id']) ? ' selected' : ''; ?>><?php echo $prH($prMethod['methods_name']); ?></option>
                <?php } ?>
            </select>
            <button type="submit" class="btn btn-default"><?php echo $prH(TEXT_PR_BUTTON_SEARCH); ?></button>
            <?php if ($prSearch !== '' || $prMethodFilter > 0) { ?>
            <a href="<?php echo $prUrl(); ?>"><?php echo $prH(TEXT_PR_BUTTON_RESET); ?></a>
            <?php } ?>
        </form>
    </div>
    <?php if ($prList['total'] === 0) { ?>
        <p><?php echo $prH(($prSearch !== '' || $prMethodFilter > 0) ? TEXT_PR_NO_MATCH : TEXT_PR_NO_RECIPES); ?></p>
    <?php } else { ?>
    <table class="pr-table">
        <thead>
        <tr>
            <th><?php echo $prH(TABLE_HEADING_PR_ID); ?></th>
            <th><?php echo $prH(TABLE_HEADING_PR_NAME); ?></th>
            <th><?php echo $prH(TABLE_HEADING_PR_METHODS); ?></th>
            <th><?php echo $prH(TABLE_HEADING_PR_INGREDIENTS); ?></th>
            <th><?php echo $prH(TABLE_HEADING_PR_LINKED); ?></th>
            <th><?php echo $prH(TABLE_HEADING_PR_SORT); ?></th>
            <th><?php echo $prH(TABLE_HEADING_PR_STATUS); ?></th>
            <th><?php echo $prH(TABLE_HEADING_PR_ACTION); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($prList['rows'] as $prRow) {
            $prId = (int)$prRow['recipes_id'];
            $prKeep = $prListParams();
            ?>
        <tr>
            <td><?php echo $prId; ?></td>
            <td><a href="<?php echo $prUrl('action=edit&rID=' . $prId); ?>"><?php echo $prH($prRow['recipes_name'] !== null && $prRow['recipes_name'] !== '' ? $prRow['recipes_name'] : '#' . $prId); ?></a></td>
            <td><?php foreach (($prRecipeMethods[$prId] ?? []) as $prMethod) { ?><span class="pr-badge"><?php echo $prH($prMethod['methods_name']); ?></span><?php } ?></td>
            <td><?php echo (int)$prRow['ingredient_count']; ?></td>
            <td><?php echo (int)$prRow['linked_count']; ?></td>
            <td><?php echo (int)$prRow['sort_order']; ?></td>
            <td>
                <?php echo zen_draw_form('prStatus' . $prId, FILENAME_PRODUCT_RECIPES, 'action=status&rID=' . $prId . ($prKeep !== '' ? '&' . $prKeep : '')); ?>
                <?php if ((int)$prRow['recipes_status'] === 1) { ?>
                    <button type="submit" class="pr-on" title="<?php echo $prH(TEXT_PR_STATUS_ON); ?>">On</button>
                <?php } else { ?>
                    <button type="submit" class="pr-off" title="<?php echo $prH(TEXT_PR_STATUS_OFF); ?>">Off</button>
                <?php } ?>
                </form>
            </td>
            <td class="pr-actions">
                <a class="btn btn-default btn-xs" href="<?php echo $prUrl('action=edit&rID=' . $prId); ?>"><?php echo $prH(TEXT_PR_BUTTON_EDIT); ?></a>
                <?php echo zen_draw_form('prCopy' . $prId, FILENAME_PRODUCT_RECIPES, 'action=copy&rID=' . $prId); ?>
                    <button type="submit" class="btn btn-default btn-xs"><?php echo $prH(TEXT_PR_BUTTON_COPY); ?></button>
                </form>
                <a class="btn btn-default btn-xs" href="<?php echo $prUrl('action=delete&rID=' . $prId . ($prKeep !== '' ? '&' . $prKeep : '')); ?>"><?php echo $prH(TEXT_PR_BUTTON_DELETE); ?></a>
                <?php if ((int)$prRow['recipes_status'] === 1) { ?>
                <a class="btn btn-default btn-xs" href="<?php echo zen_catalog_href_link(FILENAME_RECIPES, 'recipes_id=' . $prId); ?>" target="_blank" rel="noopener noreferrer"><?php echo $prH(TEXT_PR_BUTTON_VIEW); ?></a>
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php
        $prFirst = ($prPageNumber - 1) * $prPerPage + 1;
        $prLast = min($prList['total'], $prPageNumber * $prPerPage);
        $prBase = [];
        if ($prSearch !== '') {
            $prBase[] = 'q=' . urlencode($prSearch);
        }
        if ($prMethodFilter > 0) {
            $prBase[] = 'm=' . $prMethodFilter;
        }
        $prBaseParams = implode('&', $prBase);
        ?>
    <p>
        <?php echo sprintf(TEXT_PR_DISPLAY_COUNT, $prFirst, $prLast, $prList['total']); ?>
        <?php if ($prPageNumber > 1) { ?>
            &nbsp; <a href="<?php echo $prUrl(trim($prBaseParams . '&rp_page=' . ($prPageNumber - 1), '&')); ?>"><?php echo TEXT_PR_PREVIOUS; ?></a>
        <?php } ?>
        <?php if ($prLast < $prList['total']) { ?>
            &nbsp; <a href="<?php echo $prUrl(trim($prBaseParams . '&rp_page=' . ($prPageNumber + 1), '&')); ?>"><?php echo TEXT_PR_NEXT; ?></a>
        <?php } ?>
    </p>
    <?php } ?>
<?php
}

/* ====================================================================== *
 * Recipe form
 * ====================================================================== */
if ($prView === 'form') {
    $prId = (int)$prForm['recipes_id'];

    // Rows: the recipe's (or posted) non-blank rows, then blank ones to fill in. Posted rows keep
    // their index, so another plugin's fields for a row (keyed by that index) stay with it when
    // the form is shown again after Find a Product, More Rows or a failed save.
    $prRows = [];
    foreach ($prForm['rows'] as $prRowKey => $prRow) {
        if (!ProductRecipesAdmin::rowIsBlank($prRow)) {
            $prRows[(int)$prRowKey] = $prRow;
        }
    }
    $prBlank = (count($prRows) === 0 ? 5 : 2) + ($prMoreRows ? 5 : 0);
    for ($prI = 0; $prI < $prBlank; $prI++) {
        $prRows[] = [
            'id' => 0, 'amount' => '', 'unit' => [], 'text' => [], 'product' => '',
            'cart_qty' => '', 'optional' => 0, 'sort' => 0, 'remove' => 0,
        ];
    }

    // What each typed product reference points at, for the note under the box.
    $prRefIds = [];
    foreach ($prRows as $prRow) {
        $prRef = trim((string)$prRow['product']);
        if ($prRef !== '' && !isset($prRefIds[$prRef])) {
            $prRefIds[$prRef] = ProductRecipesService::resolveProductRef($prRef);
        }
    }
    $prProducts = ProductRecipesService::getProductsInfo(array_values($prRefIds), ProductRecipesService::languageId());

    $prExtraInputs = [];
    $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_ADMIN_FORM_EXTRA', $prId, $prExtraInputs);
    ?>
    <?php echo zen_draw_form('prRecipe', FILENAME_PRODUCT_RECIPES, 'action=save', 'post', 'enctype="multipart/form-data" class="pr-block"'); ?>
    <input type="hidden" name="recipes_id" value="<?php echo $prId; ?>">

    <div class="pr-panel">
        <h2><?php echo $prH(TEXT_PR_SECTION_RECIPE); ?></h2>
        <?php foreach ($prLanguages as $prLanguage) { $prLid = (int)$prLanguage['id']; ?>
        <div class="pr-field">
            <label for="prName<?php echo $prLid; ?>"><?php echo $prH(TEXT_PR_FIELD_NAME); ?><?php echo $prLanguageLabel($prLanguage); ?></label>
            <input type="text" class="form-control" id="prName<?php echo $prLid; ?>" name="recipes_name[<?php echo $prLid; ?>]" maxlength="255" value="<?php echo $prH($prForm['names'][$prLid] ?? ''); ?>">
        </div>
        <?php } ?>
        <?php foreach ($prLanguages as $prLanguage) { $prLid = (int)$prLanguage['id']; ?>
        <div class="pr-field">
            <label for="prIntro<?php echo $prLid; ?>"><?php echo $prH(TEXT_PR_FIELD_INTRO); ?><?php echo $prLanguageLabel($prLanguage); ?></label>
            <textarea class="form-control" id="prIntro<?php echo $prLid; ?>" name="recipes_intro[<?php echo $prLid; ?>]" rows="3"><?php echo $prH($prForm['intros'][$prLid] ?? ''); ?></textarea>
        </div>
        <?php } ?>
        <div class="pr-grid">
            <div><label for="prStatus"><input type="checkbox" id="prStatus" name="recipes_status" value="1"<?php echo ((int)$prForm['recipes_status'] === 1) ? ' checked' : ''; ?>> <?php echo $prH(TEXT_PR_FIELD_STATUS); ?></label></div>
            <div><label for="prSort"><?php echo $prH(TEXT_PR_FIELD_SORT); ?></label><input type="number" class="form-control" id="prSort" name="sort_order" value="<?php echo (int)$prForm['sort_order']; ?>"></div>
            <div><label for="prServings"><?php echo $prH(TEXT_PR_FIELD_SERVINGS); ?></label><input type="number" min="0" class="form-control" id="prServings" name="servings" value="<?php echo (int)$prForm['servings']; ?>"><span class="pr-note"><?php echo $prH(TEXT_PR_ZERO_HIDDEN); ?></span></div>
            <div><label for="prPrep"><?php echo $prH(TEXT_PR_FIELD_PREP); ?></label><input type="number" min="0" class="form-control" id="prPrep" name="prep_minutes" value="<?php echo (int)$prForm['prep_minutes']; ?>"></div>
            <div><label for="prCook"><?php echo $prH(TEXT_PR_FIELD_COOK); ?></label><input type="number" min="0" class="form-control" id="prCook" name="cook_minutes" value="<?php echo (int)$prForm['cook_minutes']; ?>"></div>
        </div>
        <div class="pr-field" style="margin-top:10px">
            <span style="font-weight:bold;display:block"><?php echo $prH(TEXT_PR_FIELD_METHODS); ?></span>
            <?php foreach ($prAllMethods as $prMethod) { $prMid = (int)$prMethod['methods_id']; ?>
            <label style="font-weight:normal;margin-right:14px"><input type="checkbox" name="methods[]" value="<?php echo $prMid; ?>"<?php echo in_array($prMid, $prForm['methods'], true) ? ' checked' : ''; ?>> <?php echo $prH($prMethod['methods_name']); ?><?php echo ((int)$prMethod['methods_status'] === 1) ? '' : ' (off)'; ?></label>
            <?php } ?>
        </div>
        <div class="pr-field">
            <span style="font-weight:bold;display:block"><?php echo $prH(TEXT_PR_FIELD_IMAGE); ?></span>
            <?php if ($prForm['recipes_image'] !== '') { ?>
                <img class="pr-thumb" src="<?php echo $prH((defined('DIR_WS_CATALOG_IMAGES') ? DIR_WS_CATALOG_IMAGES : DIR_WS_CATALOG . 'images/') . $prForm['recipes_image']); ?>" alt="">
                <span class="pr-note"><?php echo $prH($prForm['recipes_image']); ?></span>
                <label style="font-weight:normal"><input type="checkbox" name="remove_image" value="1"> <?php echo $prH(TEXT_PR_FIELD_IMAGE_REMOVE); ?></label>
            <?php } else { ?>
                <span class="pr-note"><?php echo $prH(TEXT_PR_FIELD_IMAGE_NONE); ?></span>
            <?php } ?>
            <label for="prImage" style="font-weight:normal"><?php echo $prH(TEXT_PR_FIELD_IMAGE_UPLOAD); ?></label>
            <input type="file" id="prImage" name="recipes_image_upload" accept=".jpg,.jpeg,.png,.gif,.webp">
        </div>
        <?php foreach ($prExtraInputs as $prExtra) { ?>
        <div class="pr-field"><label><?php echo $prExtra['label'] ?? ''; ?></label><?php echo $prExtra['input'] ?? ''; ?></div>
        <?php } ?>
    </div>

    <div class="pr-panel">
        <h2><?php echo $prH(TEXT_PR_SECTION_INGREDIENTS); ?></h2>
        <p class="pr-help"><?php echo $prH(TEXT_PR_ING_HELP); ?></p>

        <div class="pr-toolbar">
            <strong><?php echo $prH(TEXT_PR_FIND_HEADING); ?>:</strong>
            <input type="text" name="rp_find_q" value="<?php echo $prH(is_string($_POST['rp_find_q'] ?? null) ? $_POST['rp_find_q'] : ''); ?>">
            <button type="submit" name="rp_find" value="1" class="btn btn-default"><?php echo $prH(TEXT_PR_BUTTON_FIND); ?></button>
        </div>
        <?php if ($prFindResults !== null) { ?>
            <?php if ($prFindResults === []) { ?>
                <p><?php echo $prH(TEXT_PR_FIND_NONE); ?></p>
            <?php } else { ?>
                <p class="pr-help"><?php echo $prH(TEXT_PR_FIND_HELP); ?></p>
                <table class="pr-table" style="margin-bottom:12px">
                    <tr>
                        <th><?php echo $prH(TEXT_PR_FIND_COL_ID); ?></th><th><?php echo $prH(TEXT_PR_FIND_COL_MODEL); ?></th>
                        <th><?php echo $prH(TEXT_PR_FIND_COL_NAME); ?></th><th><?php echo $prH(TEXT_PR_FIND_COL_PRICE); ?></th>
                        <th><?php echo $prH(TEXT_PR_FIND_COL_STATUS); ?></th><th></th>
                    </tr>
                    <?php foreach ($prFindResults as $prFound) { ?>
                    <tr>
                        <td><?php echo (int)$prFound['products_id']; ?></td>
                        <td><?php echo $prH($prFound['products_model']); ?></td>
                        <td><?php echo $prH($prFound['products_name']); ?></td>
                        <td><?php echo $prH(number_format((float)$prFound['products_price'], 2)); ?></td>
                        <td><?php echo $prH(((int)$prFound['products_status'] === 1) ? TEXT_PR_FIND_ON : TEXT_PR_FIND_OFF); ?></td>
                        <td><button type="button" class="btn btn-default btn-xs pr-use" data-id="<?php echo (int)$prFound['products_id']; ?>"><?php echo $prH(TEXT_PR_BUTTON_USE); ?></button></td>
                    </tr>
                    <?php } ?>
                </table>
            <?php } ?>
        <?php } ?>

        <table class="pr-table pr-ing">
            <thead>
            <tr>
                <th><?php echo $prH(TEXT_PR_ING_SORT); ?></th>
                <th><?php echo $prH(TEXT_PR_ING_AMOUNT); ?></th>
                <th><?php echo $prH(TEXT_PR_ING_UNIT); ?></th>
                <th><?php echo $prH(TEXT_PR_ING_TEXT); ?></th>
                <th><?php echo $prH(TEXT_PR_ING_PRODUCT); ?></th>
                <th><?php echo $prH(TEXT_PR_ING_CART_QTY); ?></th>
                <th><?php echo $prH(TEXT_PR_ING_OPTIONAL); ?></th>
                <th><?php echo $prH(TEXT_PR_ING_REMOVE); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($prRows as $prIndex => $prRow) {
                $prName = 'ing[' . (int)$prIndex . ']';
                $prRef = trim((string)$prRow['product']);
                // For other plugins: the posted row index (it keys SAVE_END's ingredient_ids map)
                // and the product the reference resolves to (0 when none).
                $prHookRow = $prRow;
                $prHookRow['index'] = (int)$prIndex;
                $prHookRow['products_id'] = ($prRef !== '') ? (int)($prRefIds[$prRef] ?? 0) : 0;
                $prExtraCells = [];
                $zco_notifier->notify('NOTIFY_PRODUCT_RECIPES_ADMIN_INGREDIENT_ROW_EXTRA', $prHookRow, $prExtraCells);
                ?>
            <tr>
                <td>
                    <input type="hidden" name="<?php echo $prName; ?>[id]" value="<?php echo (int)$prRow['id']; ?>">
                    <input type="text" class="pr-narrow" name="<?php echo $prName; ?>[sort]" value="<?php echo ((int)$prRow['sort'] !== 0) ? (int)$prRow['sort'] : ''; ?>">
                </td>
                <td><input type="text" class="pr-narrow" name="<?php echo $prName; ?>[amount]" value="<?php echo $prH($prRow['amount']); ?>" placeholder="1 1/2"></td>
                <td>
                    <?php foreach ($prLanguages as $prLanguage) { $prLid = (int)$prLanguage['id']; ?>
                    <input type="text" class="pr-narrow" name="<?php echo $prName; ?>[unit][<?php echo $prLid; ?>]" maxlength="32" value="<?php echo $prH($prRow['unit'][$prLid] ?? ''); ?>"<?php echo $prMultiLanguage ? ' title="' . $prH($prLanguage['name']) . '"' : ''; ?>>
                    <?php } ?>
                </td>
                <td>
                    <?php foreach ($prLanguages as $prLanguage) { $prLid = (int)$prLanguage['id']; ?>
                    <input type="text" name="<?php echo $prName; ?>[text][<?php echo $prLid; ?>]" maxlength="255" value="<?php echo $prH($prRow['text'][$prLid] ?? ''); ?>"<?php echo $prMultiLanguage ? ' placeholder="' . $prH($prLanguage['name']) . '"' : ''; ?>>
                    <?php } ?>
                </td>
                <td>
                    <input type="text" class="pr-product" name="<?php echo $prName; ?>[product]" maxlength="64" value="<?php echo $prH($prRef); ?>">
                    <?php
                    if ($prRef !== '') {
                        $prPid = (int)($prRefIds[$prRef] ?? 0);
                        if ($prPid > 0 && isset($prProducts[$prPid])) {
                            echo '<span class="pr-note">' . $prH(sprintf(TEXT_PR_ING_LINKED_TO, '#' . $prPid . ' ' . $prProducts[$prPid]['name'])) . '</span>';
                            if (!$prProducts[$prPid]['active']) {
                                echo '<span class="pr-note pr-warn">' . $prH(TEXT_PR_ING_PRODUCT_INACTIVE) . '</span>';
                            } elseif (function_exists('zen_has_product_attributes') && zen_has_product_attributes($prPid)) {
                                echo '<span class="pr-note">' . $prH(TEXT_PR_ING_PRODUCT_ATTRIBUTES) . '</span>';
                            }
                        } else {
                            echo '<span class="pr-note pr-warn">' . sprintf(TEXT_PR_ING_PRODUCT_NOT_FOUND, $prH($prRef)) . '</span>';
                        }
                    }
                    ?>
                </td>
                <td><input type="text" class="pr-narrow" name="<?php echo $prName; ?>[cart_qty]" value="<?php echo $prH($prRow['cart_qty']); ?>" placeholder="1"></td>
                <td><input type="checkbox" name="<?php echo $prName; ?>[optional]" value="1"<?php echo !empty($prRow['optional']) ? ' checked' : ''; ?>></td>
                <td><input type="checkbox" name="<?php echo $prName; ?>[remove]" value="1"<?php echo !empty($prRow['remove']) ? ' checked' : ''; ?>></td>
            </tr>
            <?php if ($prExtraCells !== []) { ?>
            <tr class="pr-ing-extra">
                <td></td>
                <td colspan="7"><?php echo implode('', $prExtraCells); ?></td>
            </tr>
            <?php } ?>
            <?php } ?>
            </tbody>
        </table>
        <p style="margin-top:8px"><button type="submit" name="rp_more" value="1" class="btn btn-default"><?php echo $prH(TEXT_PR_BUTTON_MORE_ROWS); ?></button></p>
    </div>

    <div class="pr-panel">
        <h2><?php echo $prH(TEXT_PR_SECTION_INSTRUCTIONS); ?></h2>
        <?php foreach ($prLanguages as $prLanguage) { $prLid = (int)$prLanguage['id']; ?>
        <div class="pr-field">
            <label for="prInstr<?php echo $prLid; ?>"><?php echo $prH(TEXT_PR_FIELD_INSTRUCTIONS); ?><?php echo $prLanguageLabel($prLanguage); ?></label>
            <textarea class="editorHook form-control" id="prInstr<?php echo $prLid; ?>" name="recipes_instructions[<?php echo $prLid; ?>]" rows="14"><?php echo $prH($prForm['instructions'][$prLid] ?? ''); ?></textarea>
        </div>
        <?php } ?>
        <?php foreach ($prLanguages as $prLanguage) { $prLid = (int)$prLanguage['id']; ?>
        <div class="pr-field">
            <label for="prNotes<?php echo $prLid; ?>"><?php echo $prH(TEXT_PR_FIELD_NOTES); ?><?php echo $prLanguageLabel($prLanguage); ?></label>
            <textarea class="form-control" id="prNotes<?php echo $prLid; ?>" name="recipes_notes[<?php echo $prLid; ?>]" rows="4"><?php echo $prH($prForm['notes'][$prLid] ?? ''); ?></textarea>
        </div>
        <?php } ?>
    </div>

    <div class="pr-toolbar">
        <button type="submit" name="rp_save" value="1" class="btn btn-primary"><?php echo $prH(TEXT_PR_BUTTON_SAVE); ?></button>
        <button type="submit" name="rp_save_stay" value="1" class="btn btn-default"><?php echo $prH(TEXT_PR_BUTTON_SAVE_STAY); ?></button>
        <a class="btn btn-default" href="<?php echo $prUrl($prListParams()); ?>"><?php echo $prH(TEXT_PR_BUTTON_CANCEL); ?></a>
        <?php if ($prId > 0) { ?>
        <a href="<?php echo zen_catalog_href_link(FILENAME_RECIPES, 'recipes_id=' . $prId); ?>" target="_blank" rel="noopener noreferrer"><?php echo $prH(TEXT_PR_BUTTON_VIEW); ?></a>
        <?php } ?>
    </div>
    </form>
    <script>
    (function () {
        var last = null;
        var boxes = document.querySelectorAll('#prPage .pr-product');
        for (var i = 0; i < boxes.length; i++) {
            boxes[i].addEventListener('focus', function () { last = this; });
        }
        var buttons = document.querySelectorAll('#prPage .pr-use');
        for (var j = 0; j < buttons.length; j++) {
            buttons[j].addEventListener('click', function () {
                var target = last;
                if (!target) {
                    for (var k = 0; k < boxes.length; k++) {
                        if (boxes[k].value === '') { target = boxes[k]; break; }
                    }
                }
                if (target) {
                    target.value = this.getAttribute('data-id');
                    target.focus();
                }
            });
        }
    })();
    </script>
<?php
}

/* ====================================================================== *
 * Delete confirmation
 * ====================================================================== */
if ($prView === 'delete') {
    $prKeep = $prListParams();
    ?>
    <div class="pr-panel">
        <p><?php echo sprintf(TEXT_PR_DELETE_CONFIRM, $prH($prDeleteName)); ?></p>
        <?php echo zen_draw_form('prDelete', FILENAME_PRODUCT_RECIPES, 'action=delete&rID=' . $prRecipeId . ($prKeep !== '' ? '&' . $prKeep : ''), 'post', 'class="pr-block"'); ?>
            <button type="submit" class="btn btn-danger"><?php echo $prH(TEXT_PR_BUTTON_DELETE); ?></button>
            <a class="btn btn-default" href="<?php echo $prUrl($prKeep); ?>"><?php echo $prH(TEXT_PR_BUTTON_CANCEL); ?></a>
        </form>
    </div>
<?php
}

/* ====================================================================== *
 * Cooking methods
 * ====================================================================== */
if ($prView === 'methods') {
    $prMethods = $prAdmin->methodsForEditing();
    ?>
    <p class="pr-help"><?php echo $prH(TEXT_PR_METHODS_HELP); ?></p>
    <p><a href="<?php echo $prUrl(); ?>"><?php echo $prH(TEXT_PR_BUTTON_BACK); ?></a></p>
    <?php echo zen_draw_form('prMethods', FILENAME_PRODUCT_RECIPES, 'action=save_methods', 'post', 'class="pr-block"'); ?>
    <table class="pr-table">
        <thead>
        <tr>
            <th><?php echo $prH(TEXT_PR_METHODS_NAME); ?></th>
            <th><?php echo $prH(TABLE_HEADING_PR_SORT); ?></th>
            <th><?php echo $prH(TEXT_PR_METHODS_ACTIVE); ?></th>
            <th><?php echo $prH(TEXT_PR_METHODS_RECIPES); ?></th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($prMethods === []) { ?>
        <tr><td colspan="5"><?php echo $prH(TEXT_PR_METHODS_NONE); ?></td></tr>
        <?php } ?>
        <?php foreach ($prMethods as $prMid => $prMethod) { ?>
        <tr>
            <td>
                <?php foreach ($prLanguages as $prLanguage) { $prLid = (int)$prLanguage['id']; ?>
                <input type="text" name="method_name[<?php echo (int)$prMid; ?>][<?php echo $prLid; ?>]" maxlength="64" value="<?php echo $prH($prMethod['names'][$prLid] ?? ''); ?>"><?php echo $prLanguageLabel($prLanguage); ?><br>
                <?php } ?>
            </td>
            <td><input type="number" style="width:6em" name="method_sort[<?php echo (int)$prMid; ?>]" value="<?php echo (int)$prMethod['sort_order']; ?>"></td>
            <td><input type="checkbox" name="method_status[<?php echo (int)$prMid; ?>]" value="1"<?php echo ((int)$prMethod['methods_status'] === 1) ? ' checked' : ''; ?>></td>
            <td><?php echo (int)$prMethod['recipe_count']; ?></td>
            <td>
                <button type="submit" class="btn btn-default btn-xs"
                        formaction="<?php echo $prUrl('action=delete_method&mID=' . (int)$prMid); ?>"
                        onclick="return confirm(<?php echo $prH(json_encode(TEXT_PR_METHODS_DELETE_CONFIRM)); ?>);"><?php echo $prH(TEXT_PR_BUTTON_DELETE); ?></button>
            </td>
        </tr>
        <?php } ?>
        <tr>
            <td>
                <strong><?php echo $prH(TEXT_PR_METHODS_NEW); ?></strong><br>
                <?php foreach ($prLanguages as $prLanguage) { $prLid = (int)$prLanguage['id']; ?>
                <input type="text" name="new_method_name[<?php echo $prLid; ?>]" maxlength="64" value=""><?php echo $prLanguageLabel($prLanguage); ?><br>
                <?php } ?>
            </td>
            <td><input type="number" style="width:6em" name="new_method_sort" value="<?php echo (count($prMethods) + 1) * 10; ?>"></td>
            <td colspan="3"></td>
        </tr>
        </tbody>
    </table>
    <p style="margin-top:10px"><button type="submit" class="btn btn-primary"><?php echo $prH(TEXT_PR_BUTTON_SAVE_METHODS); ?></button></p>
    </form>
<?php
}
?>
</div>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
