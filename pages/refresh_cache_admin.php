<?php

namespace AEHRC\SimpleOntologyExternalModule;

// Access to this control-center page falls back to
// AbstractExternalModule::redcap_module_link_check_display()'s default
// behavior (super users only), which this module does not override for
// this link - see design.md Decision 4. The redcap_module_ajax() handler
// re-checks super-user status independently, since ajax requests don't
// route through that hook.

/** @var SimpleOntologyExternalModule $module */

$categories = $module->getSystemCategories();
$pending = $module->getCacheRefreshPending(null);

$cacheRefreshJsUrl = $module->getUrl('js/cache-refresh.js');
$jsModuleObjectName = $module->getJavascriptModuleObjectName();
?>
<?php echo $module->initializeJavascriptModuleObject(); ?>
<script src="<?php echo $cacheRefreshJsUrl; ?>"></script>

<h4>Refresh Ontology Cache - Site-wide Categories</h4>
<p>
    A site-wide ontology category can be cached separately by every project that has ever used it.
    Editing a category's values here does not update those caches. Select a category below to find
    and correct stale entries across every project.
</p>
<p>
    <strong>Note:</strong> a project that has defined its own project-level category with the same
    name is skipped here - refresh that project's cache from its own project page instead, using
    its own values.
</p>

<?php if (empty($categories)): ?>
    <p><em>No site-wide ontology categories are configured.</em></p>
<?php else: ?>
    <div id="cache_refresh_app"
         data-scope="system"
         data-module-object="<?php echo \REDCap::escapeHtml($jsModuleObjectName); ?>">
        <div>
            <label for="cache_refresh_category">Category:</label>
            <select id="cache_refresh_category">
                <option value="">-- select a category --</option>
                <?php foreach ($categories as $cat): ?>
                    <?php $isPending = in_array($cat['category'], $pending, true); ?>
                    <option value="<?php echo \REDCap::escapeHtml($cat['category']); ?>" <?php echo $isPending ? 'selected' : ''; ?>>
                        <?php echo \REDCap::escapeHtml($cat['name']); ?><?php echo $isPending ? ' (values changed - refresh recommended)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="cache_refresh_preview">Preview</button>
        </div>

        <div id="cache_refresh_status"></div>
        <div id="cache_refresh_results"></div>

        <button type="button" id="cache_refresh_apply" disabled>Apply Selected</button>
    </div>
<?php endif; ?>
