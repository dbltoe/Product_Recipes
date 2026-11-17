<?php
/**
 * Product Recipes -- plugin manifest.
 *
 * @package  ProductRecipes
 * @license  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0
 */

/**
 * Buttons shown in the Plugin Manager's info box: ReadMe | Settings | Forum Thread | GitHub.
 *
 * Zen Cart echoes pluginDescription into that box as raw HTML on every release from 1.5.8 to
 * 3.0.0, so the buttons sit beside Install / Uninstall with no core file changed.
 *
 * ReadMe opens README.md through the plugin's own admin page (index.php?cmd=product_recipes_readme):
 * behind the admin login, superusers only, in its own window. Forum Thread and GitHub go off-site,
 * so they open in a new window with rel="noopener noreferrer" (zencart/zencart#8031).
 *
 * Zen Cart 3.0.0 reads every manifest on every admin page, early, before admin access is set up:
 * zen_is_superuser() there would be a fatal "Undefined constant SUPERUSER_PROFILE" that takes down
 * the whole admin. Ask only once that exists.
 */
$prReadmeUrl = 'index.php?cmd=product_recipes_readme';
$prShowReadme = true;
if (function_exists('zen_is_superuser') && defined('SUPERUSER_PROFILE')) {
    $prShowReadme = zen_is_superuser();
}
$prGithubUrl = 'https://github.com/dbltoe/Product_Recipes';

/**
 * The Zen Cart forum support thread. PUT THE URL HERE BEFORE THE FIRST RELEASE: on 1.5.8, 2.0 and
 * 2.1 plugin_control.description is written only when Plugin Manager first sees the plugin. An
 * empty string renders no button.
 */
$prForumUrl = '';

/**
 * The Settings button: this plugin's Configuration page, shown only once the plugin is installed.
 * Its gID is assigned at install, so it's read from the admin page the installer registers (page
 * key configProductRecipes). The link is relative, so it survives a domain or admin-folder rename.
 * On 2.2 and later this file is re-read on every Plugin Manager visit; on 1.5.8, 2.0 and 2.1 the
 * installer saves the re-read description at Install, Upgrade and Uninstall.
 */
$prSettingsUrl = '';
if (defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true && defined('TABLE_ADMIN_PAGES')
    && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
    $prPage = $GLOBALS['db']->Execute(
        "SELECT main_page, page_params FROM " . TABLE_ADMIN_PAGES
        . " WHERE page_key = 'configProductRecipes' LIMIT 1"
    );
    if (!$prPage->EOF && defined($prPage->fields['main_page'])) {
        $prSettingsUrl = 'index.php?cmd=' . constant($prPage->fields['main_page'])
            . '&' . $prPage->fields['page_params'];
    }
}

// One row of buttons. 8px side padding and 4px gaps keep all four on one line in Plugin
// Manager's side panel from a 1366px-wide window up.
$prButtonGap = '4px';
$prButtonStyle = ' style="margin:0 ' . $prButtonGap . ' 0 0;padding-left:8px;padding-right:8px"';
$prButton = static function ($url, $label, $newTab) use ($prButtonStyle) {
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
        . ($newTab ? ' target="_blank" rel="noopener noreferrer"' : '')
        . ' class="btn btn-primary" role="button"' . $prButtonStyle . '>' . $label . '</a>';
};

$prLinks =
    '<div style="margin:10px 0 0;padding:0 0 0 ' . $prButtonGap . '">'
    . ($prShowReadme ? $prButton($prReadmeUrl, 'ReadMe', true) : '')
    . ($prSettingsUrl !== '' ? $prButton($prSettingsUrl, 'Settings', false) : '')
    . ($prForumUrl !== '' ? $prButton($prForumUrl, 'Forum Thread', true) : '')
    . $prButton($prGithubUrl, 'GitHub', true)
    . '</div>';

return [
    'pluginVersion' => 'v1.0.0',
    'pluginName' => 'Product Recipes',
    'pluginDescription' =>
        'Publish recipes that use the products you sell. Each ingredient can link to a product, '
        . 'showing its live price and stock, and shoppers can add one ingredient or every checked '
        . 'ingredient to the cart. Recipes are sorted by cooking method (Microwave, Bake, No Bake). '
        . 'Managed from Extras > Recipes. No template edits.'
        . $prLinks,
    // plugin_control.author is varchar(64).
    'pluginAuthor' => 'My Zen Cart Host (dbltoe)',
    // The Plugins Library's Plugin ID, a bare integer; 0 until the Library assigns one.
    'pluginId' => 0,
    'zcVersions' => ['v158', 'v200', 'v201', 'v210', 'v220', 'v221', 'v222', 'v223', 'v230', 'v300'],
    'changelog' => 'changelog.txt',
    'github_repo' => $prGithubUrl,
    'pluginGroups' => [],
];
