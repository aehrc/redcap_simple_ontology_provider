<?php

namespace AEHRC\SimpleOntologyExternalModule;

use PHPUnit\Framework\TestCase;

final class SimpleOntologyExternalModuleTest extends TestCase
{
    private SimpleOntologyExternalModule $module;

    protected function setUp(): void
    {
        \OntologyManager::resetForTests();
        $this->module = new SimpleOntologyExternalModule();
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
}
