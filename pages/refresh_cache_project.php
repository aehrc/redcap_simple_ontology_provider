<?php

namespace AEHRC\SimpleOntologyExternalModule;

// Access is gated by redcap_module_link_check_display() (see
// SimpleOntologyExternalModule.php), which the framework also enforces
// against direct navigation to this URL - see design.md Decision 4.

/** @var SimpleOntologyExternalModule $module */

$projectId = $_GET['pid'] ?? null;
$categories = $module->getProjectCategories();
$pending = $module->getCacheRefreshPending($projectId);
?>
<?php echo $module->initializeJavascriptModuleObject(); ?>
<script src="<?php echo $module->getUrl('js/cache-refresh.js'); ?>"></script>

<h4>Refresh Ontology Cache</h4>
<p>
    REDCap caches each ontology code's display text the first time a record uses it, and does not
    update that cache when you edit a category's values. If you have changed a category's display
    text, use this page to find and correct any existing records that still show the old text.
</p>

<?php echo $module->renderCacheRefreshWidget('project', $categories, $pending); ?>
