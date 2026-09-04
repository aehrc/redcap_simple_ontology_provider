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
        /** @var array<string, list<array<string, mixed>>> sub-settings for the current/ambient project context */
        public array $subSettings = [];

        /**
         * Sub-settings for a project OTHER than the ambient one, keyed by
         * project id then setting key - lets tests set up "what would
         * getSubSettings($key, $otherProjectId) return" for a project the
         * test isn't otherwise "in", the way the real framework's
         * $project_id parameter does.
         * @var array<string, array<string, list<array<string, mixed>>>>
         */
        public array $projectSubSettingsByProject = [];

        /** @var array<string, mixed> project-scoped module settings, keyed by setting key */
        public array $projectSettings = [];

        /** @var array<string, mixed> system-scoped module settings, keyed by setting key */
        public array $systemSettings = [];

        /**
         * Fake redcap_web_service_cache table, shared across all module
         * instances the way the real DB table is - never reset except by
         * ExternalModules::resetForTests().
         * @var list<array{project_id: mixed, service: string, category: string, value: string, label: string}>
         */
        public static array $webServiceCache = [];

        /** @var list<array<string, mixed>> every $this->log() call made, in order */
        public static array $logEntries = [];

        /** Matches the real framework's $this->PREFIX, normally set by module bootstrap machinery. */
        public $PREFIX = 'simple_ontology_provider';

        /** Ambient "current project" - what getProjectId() returns, and what a null $project_id resolves to. Falsy (null/'') means no project context, e.g. a Control Center page. */
        public $currentProjectId = null;

        /**
         * Setting keys that are project-scoped (declared under config.json's
         * "project-settings"). The real framework throws ("The Project Id
         * cannot be null!") if one of these is fetched with no project
         * context at all - see aehrc/redcap_simple_ontology_provider's
         * simple-ontology-cache-refresh change, where an unguarded
         * getProjectCategories() call from validateSettings() during a
         * system-level (Control Center) settings save reproduced exactly
         * this. System-scoped keys (config.json "system-settings") have no
         * such restriction.
         */
        public static array $projectScopedSettingKeys = ['project-category-list'];

        public function __construct() {}

        public function getProjectId()
        {
            return $this->currentProjectId;
        }

        public function getSubSettings($key, $project_id = null)
        {
            $effectiveProjectId = $project_id !== null ? $project_id : $this->currentProjectId;
            if (empty($effectiveProjectId) && in_array($key, self::$projectScopedSettingKeys, true)) {
                throw new \Exception('The Project Id cannot be null!');
            }

            if ($project_id !== null && isset($this->projectSubSettingsByProject[$project_id])) {
                return $this->projectSubSettingsByProject[$project_id][$key] ?? [];
            }
            return $this->subSettings[$key] ?? [];
        }

        /** Real getUrl() returns a webroot path plus a cache-busting
         *  ?filemtime for a resource file - this fake just echoes the path
         *  distinctively enough for tests to assert on. */
        public function getUrl($path)
        {
            return 'FAKE_MODULE_URL/' . $path;
        }

        public function getProjectSetting($key, $project_id = null)
        {
            return $this->projectSettings[$key] ?? null;
        }

        public function setProjectSetting($key, $value, $project_id = null)
        {
            $this->projectSettings[$key] = $value;
        }

        public function getSystemSetting($key)
        {
            return $this->systemSettings[$key] ?? null;
        }

        public function setSystemSetting($key, $value)
        {
            $this->systemSettings[$key] = $value;
        }

        public function log($message, $parameters = [])
        {
            self::$logEntries[] = ['message' => $message] + $parameters;
            return count(self::$logEntries);
        }

        public function isSuperUser()
        {
            return ExternalModules::$isSuperUser;
        }

        /**
         * Minimal stand-in for AbstractExternalModule's real default
         * (super users always pass; a project link otherwise falls back to
         * REDCap's general Project Design right, not modeled here since no
         * test currently needs that path - only the super-user path, which
         * is what this module's own override actually relies on falling
         * back to for the control-center link). Real enough that
         * `parent::redcap_module_link_check_display(...)` calls in the
         * module under test don't fatal on an undefined method.
         */
        public function redcap_module_link_check_display($project_id, $link)
        {
            return ExternalModules::$isSuperUser ? $link : null;
        }

        /**
         * Fakes the two query shapes SimpleOntologyExternalModule actually
         * issues against redcap_web_service_cache - a select (with or
         * without a project_id filter) and a single-row label update. This
         * is not a SQL parser: it dispatches on the query's leading verb and
         * assumes parameter order/position exactly as the module uses it.
         */
        public function query($sql, $parameters = [])
        {
            $normalized = trim($sql);

            if (stripos($normalized, 'select') === 0) {
                $service = $parameters[0];
                $category = $parameters[1];
                $rows = array_values(array_filter(self::$webServiceCache, function ($row) use ($service, $category) {
                    return $row['service'] === $service && $row['category'] === $category;
                }));
                if (array_key_exists(2, $parameters)) {
                    $projectId = $parameters[2];
                    $rows = array_values(array_filter($rows, function ($row) use ($projectId) {
                        return (string)$row['project_id'] === (string)$projectId;
                    }));
                }
                return new FakeQueryResult(array_map(function ($row) {
                    return ['project_id' => $row['project_id'], 'value' => $row['value'], 'label' => $row['label']];
                }, $rows));
            }

            if (stripos($normalized, 'update') === 0) {
                [$newLabel, $projectId, $service, $category, $value] = $parameters;
                foreach (self::$webServiceCache as &$row) {
                    if ((string)$row['project_id'] === (string)$projectId
                        && $row['service'] === $service
                        && $row['category'] === $category
                        && $row['value'] === $value
                    ) {
                        $row['label'] = $newLabel;
                    }
                }
                unset($row);
                return new FakeQueryResult([]);
            }

            throw new \Exception('RedcapClassFakes: unsupported fake query: ' . $sql);
        }
    }

    /** Minimal mysqli_result-alike: just enough of fetch_assoc() for this module's query() callers. */
    class FakeQueryResult
    {
        private $rows;
        private $index = 0;

        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function fetch_assoc()
        {
            if ($this->index >= count($this->rows)) {
                return null;
            }
            return $this->rows[$this->index++];
        }
    }

    class ExternalModules
    {
        public static bool $isSuperUser = false;

        /** @var array<string, bool> module prefix => has this module's per-user configuration right */
        public static array $moduleConfigurationUserRights = [];

        public static function hasModuleConfigurationUserRights($prefix)
        {
            if (self::$isSuperUser) {
                return true;
            }
            return self::$moduleConfigurationUserRights[$prefix] ?? false;
        }

        public static function resetForTests(): void
        {
            self::$isSuperUser = false;
            self::$moduleConfigurationUserRights = [];
            AbstractExternalModule::$webServiceCache = [];
            AbstractExternalModule::$logEntries = [];
        }
    }
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
