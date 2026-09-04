<?php

namespace AEHRC\SimpleOntologyExternalModule;

use PHPUnit\Framework\TestCase;

final class SimpleOntologyExternalModuleTest extends TestCase
{
    private SimpleOntologyExternalModule $module;

    protected function setUp(): void
    {
        \OntologyManager::resetForTests();
        \ExternalModules\ExternalModules::resetForTests();
        $this->module = new SimpleOntologyExternalModule();
        // Every normal field-rendering/search/settings-save entry point this
        // module exposes runs inside an actual project (a field can't exist
        // outside one) - default the fake's ambient project context to
        // reflect that. Tests exercising the one exception - a system-level
        // (Control Center) settings save, which has no project context at
        // all - override this back to null/empty explicitly.
        $this->module->currentProjectId = '1';
        \REDCap::$getDataDictionaryCallCount = 0;
        unset($_GET['field'], $_GET['pid']);
        $GLOBALS['Proj'] = null;
    }

    /** Raw site-category-list sub-setting row, matching the real config.json keys. */
    private function siteCategory(array $overrides = []): array
    {
        return array_merge([
            'site-category' => 'test-cat',
            'site-name' => 'Test Category',
            'site-search-type' => 'word',
            'site-return-no-result' => false,
            'site-no-result-label' => '',
            'site-no-result-code' => '',
            'site-values-type' => 'bar',
            'site-values' => "C1|Display One\nC2|Display Two",
        ], $overrides);
    }

    // --- getLabelForValue() ---
    // Regression coverage for GitHub issue #11: array_key_exists($value, $values)
    // checked numeric array indices, not codes, so the lookup never matched and
    // every imported/API-set value silently lost its display label.

    public function testGetLabelForValueReturnsConfiguredDisplayForBarType(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory()];

        $label = $this->module->getLabelForValue('test-cat', 'C1');

        $this->assertSame('Display One', $label);
    }

    public function testGetLabelForValueReturnsConfiguredDisplayForJsonType(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-values-type' => 'json',
            'site-values' => json_encode([
                ['code' => 'C1', 'display' => 'Display One'],
                ['code' => 'C2', 'display' => 'Display Two'],
            ]),
        ])];

        $label = $this->module->getLabelForValue('test-cat', 'C2');

        $this->assertSame('Display Two', $label);
    }

    public function testGetLabelForValueFallsBackToRawValueWhenNotFound(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory()];

        $label = $this->module->getLabelForValue('test-cat', 'does-not-exist');

        $this->assertSame('does-not-exist', $label);
    }

    public function testGetLabelForValueResolvesPreviouslyDeactivatedBarEntry(): void
    {
        // README's "Active flag" section: a deactivated entry (leading '!')
        // is still a member of the ontology and must resolve to its label
        // if a record already holds that value from before it was
        // deactivated - getLabelForValue() never stripped the '!' marker
        // that searchOntology() does, so the code map was keyed by '!col'
        // instead of 'col' and this always fell through to the raw code.
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-values-type' => 'bar',
            'site-values' => "coo|Cooper Derricks\n!col|Morty Cole",
        ])];

        $label = $this->module->getLabelForValue('test-cat', 'col');

        $this->assertSame('Morty Cole', $label);
    }

    public function testGetLabelForValueResolvesPreviouslyDeactivatedListEntry(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-values-type' => 'list',
            'site-values' => "Active One\n!Inactive One",
        ])];

        $label = $this->module->getLabelForValue('test-cat', 'Inactive One');

        $this->assertSame('Inactive One', $label);
    }

    // --- searchOntology() ---

    public function testSearchOntologyReturnsEmptyForUnknownCategoryWithoutWarning(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory()];

        $results = $this->module->searchOntology('does-not-exist', 'display', 20);

        $this->assertSame([], $results);
    }

    public function testSearchOntologyMatchesByWordAcrossDisplayText(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-values-type' => 'bar',
            'site-values' => "C1|Heart failure\nC2|Kidney failure\nC3|Broken arm",
        ])];

        $results = $this->module->searchOntology('test-cat', 'failure', 20);

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('C1', $results);
        $this->assertArrayHasKey('C2', $results);
        $this->assertArrayNotHasKey('C3', $results);
    }

    public function testSearchOntologyFiltersInactiveEntries(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-values-type' => 'list',
            'site-values' => "Active One\n!Inactive One",
        ])];

        $results = $this->module->searchOntology('test-cat', 'One', 20);

        $this->assertArrayHasKey('Active One', $results);
        $this->assertArrayNotHasKey('Inactive One', $results);
    }

    public function testSearchOntologyBarTypeRowWithoutDelimiterDoesNotWarnOrReturnNullDisplay(): void
    {
        // A bar-type row with no '|' has nothing left after popping the code
        // - previously this produced display => null, reaching
        // skip_accents()/htmlentities() with null and warning on PHP 8.1+.
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-values-type' => 'bar',
            'site-values' => "onlycode",
        ])];

        $results = $this->module->searchOntology('test-cat', 'onlycode', 20);

        $this->assertSame('onlycode', $results['onlycode']);
    }

    public function testSearchOntologyListTypeEntriesDoNotWarnOnMissingSynonyms(): void
    {
        // 'list'-type entries are built without a 'synonyms' key at all, but
        // searchOntology() reads $val['synonyms'] unconditionally for every
        // entry regardless of type - previously an undefined-array-key warning
        // on every search against a 'list'-type category.
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-values-type' => 'list',
            'site-values' => "Heart failure\nKidney failure",
        ])];

        $results = $this->module->searchOntology('test-cat', 'failure', 20);

        $this->assertCount(2, $results);
    }

    public function testSearchOntologyMinimalJsonEntriesDoNotWarnOnMissingOptionalFields(): void
    {
        // 'active' and 'synonyms' are documented as optional for the json
        // format - this is the module's own basic README example shape, with
        // neither field present.
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-values-type' => 'json',
            'site-values' => json_encode([
                ['code' => 'C1', 'display' => 'Heart failure'],
                ['code' => 'C2', 'display' => 'Kidney failure'],
            ]),
        ])];

        $results = $this->module->searchOntology('test-cat', 'failure', 20);

        $this->assertCount(2, $results);
    }

    public function testSearchOntologyReturnsRawUnescapedCodeAndDisplay(): void
    {
        // Regression coverage for GitHub issue #5: escaping the code/display
        // here meant an escaped code (e.g. "Child&#039;s Nervous System")
        // was saved into the record verbatim, since REDCap core's
        // web_service_auto_suggest.php only ever reverses HTML-escaping
        // (label_decode()) on the label, never on the value/code. REDCap's
        // own built-in BioPortalOntologyProvider returns both fields raw for
        // the same reason - label_decode()/filter_tags() in core are the
        // actual sanitization boundary.
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-values-type' => 'list',
            'site-values' => "Child's Nervous System",
        ])];

        $results = $this->module->searchOntology('test-cat', 'Nervous', 20);

        $this->assertArrayHasKey("Child's Nervous System", $results);
        $this->assertSame("Child's Nervous System", $results["Child's Nervous System"]);
    }

    public function testSearchOntologyReturnsConfiguredFallbackWhenBelowResultLimit(): void
    {
        // Note the actual semantics here: "fewer results than the requested
        // limit", not "zero results" (different from the FHIR-backed modules).
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-values-type' => 'bar',
            'site-values' => "C1|Heart failure",
            'site-return-no-result' => true,
            'site-no-result-label' => 'No Results Found',
            'site-no-result-code' => '_NRF_',
        ])];

        $results = $this->module->searchOntology('test-cat', 'failure', 20);

        $this->assertArrayHasKey('C1', $results);
        $this->assertArrayHasKey('_NRF_', $results);
        $this->assertSame('No Results Found', $results['_NRF_']);
    }

    // --- getHideChoice() ---
    // Regression coverage for a missing-`global $Proj` performance bug: the
    // same pattern is also present, and still unfixed, in both FHIR-backed
    // ontology modules' getHideChoice() as of this writing - see
    // ontology-provider-testing-framework's tasks.md.

    public function testGetHideChoiceUsesInMemoryProjectMetadataWithoutReloadingDictionary(): void
    {
        $project = new \Project();
        $project->project_id = '42';
        $project->metadata['my_field']['field_annotation'] = "@HIDECHOICE='C1,C2'";
        $GLOBALS['Proj'] = $project;
        $_GET['field'] = 'my_field';
        $_GET['pid'] = '42';

        $hidden = $this->module->getHideChoice();

        $this->assertSame(['C1', 'C2'], $hidden);
        $this->assertSame(
            0,
            \REDCap::$getDataDictionaryCallCount,
            'the in-memory $Proj fast path should have been used, not the full dictionary reload'
        );
    }

    public function testGetHideChoiceFallsBackToDataDictionaryWhenProjNotAvailable(): void
    {
        $_GET['field'] = 'my_field';
        $_GET['pid'] = '42';

        $hidden = $this->module->getHideChoice();

        $this->assertSame([], $hidden);
        $this->assertSame(1, \REDCap::$getDataDictionaryCallCount);
    }

    // --- getOnlineDesignerSection() ---
    // Regression coverage for the js/online-designer.js extraction: this
    // method's heredoc was never exercised by any test before, so a broken
    // interpolation or an inline <script> creeping back in would only have
    // been caught by a manual browser check.

    public function testOnlineDesignerSectionLoadsExtractedJsFileNotInlineScript(): void
    {
        $html = $this->module->getOnlineDesignerSection();

        $this->assertStringContainsString('<script src="FAKE_MODULE_URL/js/online-designer.js"></script>', $html);
        $this->assertStringNotContainsString('function SIMPLE_ontology_changed', $html);
    }

    public function testOnlineDesignerSectionListsConfiguredCategories(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-category' => 'cat1',
            'site-name' => 'Category One',
        ])];

        $html = $this->module->getOnlineDesignerSection();

        $this->assertStringContainsString("<option value='cat1'>Category One</option>", $html);
    }

    // --- findStaleCacheEntries() / applyCacheRefresh() ---
    // aehrc/redcap_simple_ontology_provider#10: redcap_web_service_cache is
    // never updated when a category's values change, so existing records
    // keep showing the old display. These cover the diff/apply logic that
    // lets a project or control-center page correct it - see
    // openspec/changes/simple-ontology-cache-refresh/design.md.

    /** Raw project-category-list sub-setting row, matching the real config.json keys. */
    private function projectCategory(array $overrides = []): array
    {
        return array_merge([
            'project-category' => 'test-cat',
            'project-name' => 'Test Category',
            'project-search-type' => 'word',
            'project-return-no-result' => false,
            'project-no-result-label' => '',
            'project-no-result-code' => '',
            'project-values-type' => 'bar',
            'project-values' => "C1|Display One\nC2|Display Two",
        ], $overrides);
    }

    private function cacheRow($projectId, $category, $value, $label, $service = 'SIMPLE'): array
    {
        return ['project_id' => $projectId, 'service' => $service, 'category' => $category, 'value' => $value, 'label' => $label];
    }

    public function testFindStaleCacheEntriesProjectScopeOnlyDiffsRowsForGivenProject(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory()];
        \ExternalModules\AbstractExternalModule::$webServiceCache = [
            $this->cacheRow('42', 'test-cat', 'C1', 'Old Display One'),
            $this->cacheRow('99', 'test-cat', 'C1', 'Some Other Projects Old Label'),
        ];

        $result = $this->module->findStaleCacheEntries('test-cat', '42');

        $this->assertCount(1, $result['stale']);
        $this->assertSame('42', $result['stale'][0]['project_id']);
        $this->assertSame('C1', $result['stale'][0]['value']);
        $this->assertSame('Old Display One', $result['stale'][0]['old_label']);
        $this->assertSame('Display One', $result['stale'][0]['new_label']);
        $this->assertSame([], $result['skippedProjects']);
    }

    public function testFindStaleCacheEntriesSystemScopeSkipsProjectThatOverridesCategory(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory([
            'site-category' => 'shared-cat',
            'site-values' => "C1|System Display One",
        ])];
        \ExternalModules\AbstractExternalModule::$webServiceCache = [
            $this->cacheRow('1', 'shared-cat', 'C1', 'Old System Label'),
            $this->cacheRow('2', 'shared-cat', 'C1', 'Old Overridden Label'),
        ];

        // Project 2 has defined its own project-level category with the same name.
        $this->setProjectSubSettingsFor('2', 'project-category-list', [$this->projectCategory([
            'project-category' => 'shared-cat',
            'project-values' => "C1|Project Overridden Display",
        ])]);

        $result = $this->module->findStaleCacheEntries('shared-cat', null);

        $this->assertCount(1, $result['stale']);
        $this->assertSame('1', $result['stale'][0]['project_id']);
        $this->assertSame('System Display One', $result['stale'][0]['new_label']);

        $this->assertCount(1, $result['skippedProjects']);
        $this->assertSame('2', $result['skippedProjects'][0]['project_id']);
    }

    public function testFindStaleCacheEntriesExcludesCodeNoLongerInCategoryDefinition(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory([
            'project-values' => "C1|Display One",
        ])];
        \ExternalModules\AbstractExternalModule::$webServiceCache = [
            $this->cacheRow('42', 'test-cat', 'C1', 'Old Display One'),
            $this->cacheRow('42', 'test-cat', 'removed-code', 'Whatever It Used To Say'),
        ];

        $result = $this->module->findStaleCacheEntries('test-cat', '42');

        $this->assertCount(1, $result['stale']);
        $this->assertSame('C1', $result['stale'][0]['value']);
    }

    public function testFindStaleCacheEntriesExcludesEntryWhoseLabelAlreadyMatches(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory()];
        \ExternalModules\AbstractExternalModule::$webServiceCache = [
            $this->cacheRow('42', 'test-cat', 'C1', 'Display One'),
        ];

        $result = $this->module->findStaleCacheEntries('test-cat', '42');

        $this->assertSame([], $result['stale']);
    }

    public function testApplyCacheRefreshUpdatesCacheAndReturnsWhatChanged(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory()];
        \ExternalModules\AbstractExternalModule::$webServiceCache = [
            $this->cacheRow('42', 'test-cat', 'C1', 'Old Display One'),
        ];

        $updated = $this->module->applyCacheRefresh('test-cat', '42', [
            ['project_id' => '42', 'value' => 'C1'],
        ]);

        $this->assertCount(1, $updated);
        $this->assertSame('Display One', $updated[0]['new_label']);
        $this->assertSame('Display One', \ExternalModules\AbstractExternalModule::$webServiceCache[0]['label']);
    }

    public function testApplyCacheRefreshReDerivesLabelAtApplyTimeRatherThanTrustingCallerSnapshot(): void
    {
        // The category changes again between "preview" and "apply" - apply
        // must write what's true now, not whatever the (now stale) preview said.
        $this->module->subSettings['project-category-list'] = [$this->projectCategory([
            'project-values' => "C1|Brand New Display",
        ])];
        \ExternalModules\AbstractExternalModule::$webServiceCache = [
            $this->cacheRow('42', 'test-cat', 'C1', 'Old Display One'),
        ];

        // Caller passes a stale/fabricated old_label/new_label - applyCacheRefresh must ignore them.
        $updated = $this->module->applyCacheRefresh('test-cat', '42', [
            ['project_id' => '42', 'value' => 'C1', 'old_label' => 'Old Display One', 'new_label' => 'A Value The Caller Made Up'],
        ]);

        $this->assertSame('Brand New Display', $updated[0]['new_label']);
        $this->assertSame('Brand New Display', \ExternalModules\AbstractExternalModule::$webServiceCache[0]['label']);
    }

    public function testApplyCacheRefreshDoesNotUpdateEntryNoLongerStale(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory()];
        \ExternalModules\AbstractExternalModule::$webServiceCache = [
            $this->cacheRow('42', 'test-cat', 'C1', 'Display One'),
        ];

        $updated = $this->module->applyCacheRefresh('test-cat', '42', [
            ['project_id' => '42', 'value' => 'C1'],
        ]);

        $this->assertSame([], $updated);
    }

    public function testApplyCacheRefreshLogsEachUpdatedRow(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory()];
        \ExternalModules\AbstractExternalModule::$webServiceCache = [
            $this->cacheRow('42', 'test-cat', 'C1', 'Old Display One'),
        ];

        $this->module->applyCacheRefresh('test-cat', '42', [
            ['project_id' => '42', 'value' => 'C1'],
        ]);

        $this->assertCount(1, \ExternalModules\AbstractExternalModule::$logEntries);
        $logged = \ExternalModules\AbstractExternalModule::$logEntries[0];
        $this->assertSame('test-cat', $logged['category']);
        $this->assertSame('C1', $logged['value']);
        $this->assertSame('Old Display One', $logged['old_label']);
        $this->assertSame('Display One', $logged['new_label']);
        $this->assertSame('42', $logged['project_id']);
    }

    // --- redcap_module_ajax() authorization ---

    public function testRedcapModuleAjaxRejectsControlCenterActionForNonSuperUser(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory()];
        \ExternalModules\ExternalModules::$isSuperUser = false;

        $response = $this->module->redcap_module_ajax('preview-cache-refresh', ['category' => 'test-cat'], null);

        $this->assertArrayHasKey('error', $response);
    }

    public function testRedcapModuleAjaxAllowsControlCenterActionForSuperUser(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->siteCategory()];
        \ExternalModules\ExternalModules::$isSuperUser = true;

        $response = $this->module->redcap_module_ajax('preview-cache-refresh', ['category' => 'test-cat'], null);

        $this->assertArrayNotHasKey('error', $response);
    }

    public function testRedcapModuleAjaxRejectsProjectActionForUserWithoutModuleConfigurationRights(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory()];
        \ExternalModules\ExternalModules::$moduleConfigurationUserRights = [];

        $response = $this->module->redcap_module_ajax('preview-cache-refresh', ['category' => 'test-cat'], '42');

        $this->assertArrayHasKey('error', $response);
    }

    public function testRedcapModuleAjaxAllowsProjectActionForUserWithModuleConfigurationRights(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory()];
        \ExternalModules\ExternalModules::$moduleConfigurationUserRights = ['simple_ontology_provider' => true];

        $response = $this->module->redcap_module_ajax('preview-cache-refresh', ['category' => 'test-cat'], '42');

        $this->assertArrayNotHasKey('error', $response);
    }

    /** Loads a project-scoped sub-setting for a specific project id, independent of the current-context subSettings array. */
    private function setProjectSubSettingsFor($projectId, $key, array $rows): void
    {
        $this->module->projectSubSettingsByProject[$projectId][$key] = $rows;
    }

    // --- Save-time "you may need to refresh the cache" reminder ---
    // Regression coverage for a real bug hit while manually testing: saving
    // module settings from the Control Center (a system-level save, with no
    // project context at all) threw "The Project Id cannot be null!" from
    // deep inside the framework, because validateSettings()'s snapshot
    // unconditionally called getProjectCategories() - a project-scoped
    // settings lookup the real framework refuses to make with no project id
    // available. getSystemCategories() has no such restriction.

    /**
     * A full validateSettings() payload with every key the real save-settings
     * form always submits (per config.json), covering only $siteValues/
     * $projectValues as single-category lists for simplicity - callers pass
     * [] for whichever side has no categories.
     */
    private function settingsPayload(array $siteValues = [], array $projectValues = []): array
    {
        $blank = ['category' => [], 'name' => [], 'search-type' => [], 'return-no-result' => [],
            'no-result-label' => [], 'no-result-code' => [], 'values-type' => [], 'values' => []];
        $fill = function (array $overrides) use ($blank) {
            return array_merge($blank, $overrides);
        };
        $site = $fill($siteValues);
        $project = $fill($projectValues);
        return [
            'site-category' => $site['category'], 'site-name' => $site['name'],
            'site-search-type' => $site['search-type'], 'site-return-no-result' => $site['return-no-result'],
            'site-no-result-label' => $site['no-result-label'], 'site-no-result-code' => $site['no-result-code'],
            'site-values-type' => $site['values-type'], 'site-values' => $site['values'],
            'project-category' => $project['category'], 'project-name' => $project['name'],
            'project-search-type' => $project['search-type'], 'project-return-no-result' => $project['return-no-result'],
            'project-no-result-label' => $project['no-result-label'], 'project-no-result-code' => $project['no-result-code'],
            'project-values-type' => $project['values-type'], 'project-values' => $project['values'],
        ];
    }

    public function testValidateSettingsDoesNotThrowWhenSavedFromControlCenterWithNoProjectContext(): void
    {
        $this->module->currentProjectId = null;
        $this->module->subSettings['site-category-list'] = [$this->siteCategory()];

        $errors = $this->module->validateSettings($this->settingsPayload([
            'category' => ['test-cat'], 'name' => ['Test Category'],
            'return-no-result' => [false], 'values-type' => ['bar'], 'values' => ["C1|Display One"],
        ]));

        $this->assertSame('', $errors);
    }

    public function testRedcapModuleSaveConfigurationDoesNotThrowForSystemLevelSaveWithNoProjectContext(): void
    {
        $this->module->currentProjectId = null;
        $this->module->subSettings['site-category-list'] = [$this->siteCategory()];
        $this->module->validateSettings($this->settingsPayload([
            'category' => ['test-cat'], 'name' => ['Test Category'],
            'return-no-result' => [false], 'values-type' => ['bar'], 'values' => ["C1|Display One"],
        ]));

        // Simulate the value actually changing before redcap_module_save_configuration() runs.
        $this->module->subSettings['site-category-list'] = [$this->siteCategory(['site-values' => "C1|Changed Display"])];

        $this->module->redcap_module_save_configuration('');

        $this->assertSame(['test-cat'], $this->module->getCacheRefreshPending(null));
    }

    public function testSaveConfigurationFlagsCategoryWhoseValuesChanged(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory()];
        $this->module->validateSettings($this->settingsPayload([], [
            'category' => ['test-cat'], 'name' => ['Test Category'], 'return-no-result' => [false],
            'values-type' => ['bar'], 'values' => ["C1|Display One\nC2|Display Two"],
        ]));

        $this->module->subSettings['project-category-list'] = [$this->projectCategory([
            'project-values' => "C1|Display One Updated\nC2|Display Two",
        ])];

        $this->module->redcap_module_save_configuration('42');

        $this->assertSame(['test-cat'], $this->module->getCacheRefreshPending('42'));
    }

    public function testSaveConfigurationDoesNotFlagCategoryWhenValuesUnchanged(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory()];
        $this->module->validateSettings($this->settingsPayload([], [
            'category' => ['test-cat'], 'name' => ['Test Category'], 'return-no-result' => [false],
            'values-type' => ['bar'], 'values' => ["C1|Display One\nC2|Display Two"],
        ]));

        // No change to subSettings before the save-configuration hook runs.
        $this->module->redcap_module_save_configuration('42');

        $this->assertSame([], $this->module->getCacheRefreshPending('42'));
    }

    public function testApplyCacheRefreshClearsPendingReminderForCategory(): void
    {
        $this->module->subSettings['project-category-list'] = [$this->projectCategory()];
        $this->module->setProjectSetting('cache-refresh-pending', json_encode(['test-cat', 'other-cat']));

        $this->module->applyCacheRefresh('test-cat', '42', []);

        $this->assertSame(['other-cat'], $this->module->getCacheRefreshPending('42'));
    }

    public function testConfigurationSettingsInjectsReminderWhenCategoryPending(): void
    {
        $this->module->setProjectSetting('cache-refresh-pending', json_encode(['test-cat']));

        $settings = $this->module->redcap_module_configuration_settings('42', [
            ['key' => 'project-category-list', 'name' => 'List of Ontologies for the project'],
        ]);

        $this->assertSame('descriptive', $settings[0]['type']);
        $this->assertStringContainsString('test-cat', $settings[0]['name']);
        $this->assertSame('project-category-list', $settings[1]['key']);
    }

    public function testConfigurationSettingsUnchangedWhenNothingPending(): void
    {
        $original = [['key' => 'project-category-list', 'name' => 'List of Ontologies for the project']];

        $settings = $this->module->redcap_module_configuration_settings('42', $original);

        $this->assertSame($original, $settings);
    }
}
