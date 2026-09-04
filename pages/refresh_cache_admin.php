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
?>
<?php echo $module->initializeJavascriptModuleObject(); ?>
<script src="<?php echo $module->getUrl('js/cache-refresh.js'); ?>"></script>

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

<?php echo $module->renderCacheRefreshWidget('system', $categories, $pending); ?>
