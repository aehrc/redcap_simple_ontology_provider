<?php

/**
 * Test-only fakes for the REDCap External Modules framework, with real (if
 * minimal) behavior - unlike stubs/redcap-em-framework.phpstub, which is
 * signatures-only for Psalm. These must never be loaded outside tests: they're
 * deliberately simplistic (in-memory arrays, no validation) and would be
 * actively wrong as production code.
 *
 * This module makes no outbound HTTP calls at all (static list provider), so
 * unlike the two FHIR-backed ontology modules there's no HTTP-faking layer
 * here at all - just these class fakes.
 */

namespace ExternalModules {
    abstract class AbstractExternalModule
    {
        /** @var array<string, list<array<string, mixed>>> */
        public array $subSettings = [];

        public function __construct() {}

        public function getSubSettings($key, $project_id = null)
        {
            return $this->subSettings[$key] ?? [];
        }

        /** Real getUrl() returns a webroot path plus a cache-busting
         *  ?filemtime for a resource file - this fake just echoes the path
         *  distinctively enough for tests to assert on. */
        public function getUrl($path)
        {
            return 'FAKE_MODULE_URL/' . $path;
        }
    }

    class ExternalModules {}
}

namespace {

    class REDCap
    {
        public static function escapeHtml($value)
        {
            return htmlspecialchars((string)$value, ENT_QUOTES);
        }

        /** @var int Call counter so tests can assert the $Proj fast path in
         *  getHideChoice() avoided this full-dictionary-reload path. */
        public static int $getDataDictionaryCallCount = 0;

        public static function getDataDictionary($project_id, $format = 'array', $numeric = false, $fields = null, $forms = null)
        {
            self::$getDataDictionaryCallCount++;
            return [];
        }
    }

    interface OntologyProvider
    {
        public function searchOntology($category, $search_term, $result_limit);
        public function getServicePrefix();
        public function getProviderName();
        public function getLabelForValue($category, $value);
        public function getOnlineDesignerSection();
    }

    class OntologyManager
    {
        private static ?OntologyManager $instance = null;

        /** @var list<object> */
        public array $providers = [];

        public static function getOntologyManager(): self
        {
            return self::$instance ??= new self();
        }

        public function addProvider($provider): void
        {
            $this->providers[] = $provider;
        }

        public static function resetForTests(): void
        {
            self::$instance = null;
        }
    }

    class Project
    {
        public $project_id;
        public $metadata = [];
    }
}
