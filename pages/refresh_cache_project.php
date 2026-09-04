<?php

namespace AEHRC\SimpleOntologyExternalModule;

// Access is gated by redcap_module_link_check_display() (see
// SimpleOntologyExternalModule.php), which the framework also enforces
// against direct navigation to this URL - see design.md Decision 4.

/** @var SimpleOntologyExternalModule $module */

$projectId = $_GET['pid'] ?? null;
$categories = $module->getProjectCategories();
$pending = $module->getCacheRefreshPending($projectId);

$cacheRefreshJsUrl = $module->getUrl('js/cache-refresh.js');
$jsModuleObjectName = $module->getJavascriptModuleObjectName();
?>
<?php echo $module->initializeJavascriptModuleObject(); ?>
<script src="<?php echo $cacheRefreshJsUrl; ?>"></script>

<h4>Refresh Ontology Cache</h4>
<p>
    REDCap caches each ontology code's display text the first time a record uses it, and does not
    update that cache when you edit a category's values. If you have changed a category's display
    text, use this page to find and correct any existing records that still show the old text.
</p>

<?php if (empty($categories)): ?>
    <p><em>No project-level ontology categories are configured for this project.</em></p>
<?php else: ?>
    <div id="cache_refresh_app"
         data-scope="project"
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
