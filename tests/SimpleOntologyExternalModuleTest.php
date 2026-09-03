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
    // Regression coverage for the same missing-`global $Proj` performance bug
    // already found and fixed in both FHIR-backed ontology modules during the
    // original security audit - never checked against this module's own copy
    // of the same pattern until now.

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
}
