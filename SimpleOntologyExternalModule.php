<?php
/**
 *
 * CSIRO Open Source Software Licence Agreement (variation of the BSD / MIT License)
 * Copyright (c) 2018, Commonwealth Scientific and Industrial Research Organisation (CSIRO) ABN 41 687 119 230.
 * All rights reserved. CSIRO is willing to grant you a licence to this SimpleOntologyExternalModule on the following terms, except where otherwise indicated for third party material.
 * Redistribution and use of this software in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * Neither the name of CSIRO nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission of CSIRO.
 * EXCEPT AS EXPRESSLY STATED IN THIS AGREEMENT AND TO THE FULL EXTENT PERMITTED BY APPLICABLE LAW, THE SOFTWARE IS PROVIDED "AS-IS". CSIRO MAKES NO REPRESENTATIONS, WARRANTIES OR CONDITIONS OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO ANY REPRESENTATIONS, WARRANTIES OR CONDITIONS REGARDING THE CONTENTS OR ACCURACY OF THE SOFTWARE, OR OF TITLE, MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, NON-INFRINGEMENT, THE ABSENCE OF LATENT OR OTHER DEFECTS, OR THE PRESENCE OR ABSENCE OF ERRORS, WHETHER OR NOT DISCOVERABLE.
 * TO THE FULL EXTENT PERMITTED BY APPLICABLE LAW, IN NO EVENT SHALL CSIRO BE LIABLE ON ANY LEGAL THEORY (INCLUDING, WITHOUT LIMITATION, IN AN ACTION FOR BREACH OF CONTRACT, NEGLIGENCE OR OTHERWISE) FOR ANY CLAIM, LOSS, DAMAGES OR OTHER LIABILITY HOWSOEVER INCURRED.  WITHOUT LIMITING THE SCOPE OF THE PREVIOUS SENTENCE THE EXCLUSION OF LIABILITY SHALL INCLUDE: LOSS OF PRODUCTION OR OPERATION TIME, LOSS, DAMAGE OR CORRUPTION OF DATA OR RECORDS; OR LOSS OF ANTICIPATED SAVINGS, OPPORTUNITY, REVENUE, PROFIT OR GOODWILL, OR OTHER ECONOMIC LOSS; OR ANY SPECIAL, INCIDENTAL, INDIRECT, CONSEQUENTIAL, PUNITIVE OR EXEMPLARY DAMAGES, ARISING OUT OF OR IN CONNECTION WITH THIS AGREEMENT, ACCESS OF THE SOFTWARE OR ANY OTHER DEALINGS WITH THE SOFTWARE, EVEN IF CSIRO HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH CLAIM, LOSS, DAMAGES OR OTHER LIABILITY.
 * APPLICABLE LEGISLATION SUCH AS THE AUSTRALIAN CONSUMER LAW MAY APPLY REPRESENTATIONS, WARRANTIES, OR CONDITIONS, OR IMPOSES OBLIGATIONS OR LIABILITY ON CSIRO THAT CANNOT BE EXCLUDED, RESTRICTED OR MODIFIED TO THE FULL EXTENT SET OUT IN THE EXPRESS TERMS OF THIS CLAUSE ABOVE "CONSUMER GUARANTEES".  TO THE EXTENT THAT SUCH CONSUMER GUARANTEES CONTINUE TO APPLY, THEN TO THE FULL EXTENT PERMITTED BY THE APPLICABLE LEGISLATION, THE LIABILITY OF CSIRO UNDER THE RELEVANT CONSUMER GUARANTEE IS LIMITED (WHERE PERMITTED AT CSIRO'S OPTION) TO ONE OF FOLLOWING REMEDIES OR SUBSTANTIALLY EQUIVALENT REMEDIES:
 * (a)               THE REPLACEMENT OF THE SOFTWARE, THE SUPPLY OF EQUIVALENT SOFTWARE, OR SUPPLYING RELEVANT SERVICES AGAIN;
 * (b)               THE REPAIR OF THE SOFTWARE;
 * (c)               THE PAYMENT OF THE COST OF REPLACING THE SOFTWARE, OF ACQUIRING EQUIVALENT SOFTWARE, HAVING THE RELEVANT SERVICES SUPPLIED AGAIN, OR HAVING THE SOFTWARE REPAIRED.
 * IN THIS CLAUSE, CSIRO INCLUDES ANY THIRD PARTY AUTHOR OR OWNER OF ANY PART OF THE SOFTWARE OR MATERIAL DISTRIBUTED WITH IT.  CSIRO MAY ENFORCE ANY RIGHTS ON BEHALF OF THE RELEVANT THIRD PARTY.
 * Third Party Components
 * The following third party components are distributed with the Software.  You agree to comply with the licence terms for these components as part of accessing the Software.  Other third party software may also be identified in separate files distributed with the Software.
 *
 *
 *
 */

namespace AEHRC\SimpleOntologyExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class SimpleOntologyExternalModule extends AbstractExternalModule implements \OntologyProvider
{
    /**
     * Snapshot of every category's 'values' string, keyed by category name,
     * taken by validateSettings() just before a save commits. Read back by
     * redcap_module_save_configuration() - within that one save request the
     * EM framework hands both calls the same module instance (see design.md
     * Decision 5), so this instance property survives from one to the other.
     */
    private $categoriesBeforeSave = null;

    public function __construct()
    {
        parent::__construct();
        // register with OntologyManager
        $manager = \OntologyManager::getOntologyManager();
        $manager->addProvider($this);
    }

    public function redcap_every_page_before_render($project_id)
    {
    }

    public function validateSettings($settings)
    {
        $this->categoriesBeforeSave = [];
        foreach ($this->allCurrentCategories() as $cat) {
            $this->categoriesBeforeSave[$cat['category']] = $cat['values'];
        }

        $errors = '';

        // make sure category has no markup or ' " char
        $siteCategory = $settings['site-category'];
        foreach ($siteCategory as $category) {
            if ($category != strip_tags($category)
                || strpos($category, "'") !== false
                || strpos($category, '"') !== false
            ) {
                $errors .= "Category has illegal characters - " . $category . "\n";
            }
        }
        $projectCategory = $settings['project-category'];
        foreach ($projectCategory as $category) {
            if ($category != strip_tags($category)
                || strpos($category, "'") !== false
                || strpos($category, '"') !== false
            ) {
                $errors .= "Category has illegal characters - " . $category . "\n";
            }
        }
        // make sure name has no markup
        foreach ($settings['site-name'] as $name) {
            if ($name != strip_tags($name)) {
                $errors .= "Name has illegal characters - " . $name . "\n";
            }
        }
        foreach ($settings['project-name'] as $name) {
            if ($name != strip_tags($name)) {
                $errors .= "Name has illegal characters - " . $name . "\n";
            }
        }

        // If value is json make sure its valid json and is an array
        $values = $settings['site-values'];
        foreach ($settings['site-values-type'] as $key => $valueType) {
            if ($valueType == 'json') {
                // check json is valid
                $rawValue = $values[$key];
                $list = json_decode($rawValue);
                if (is_null($list)) {
                    $errors .= "Invalid JSon [" . $siteCategory[$key] . "] : " . json_last_error_msg() . "\n";
                } else if (!is_array($list)) {
                    $errors .= "Invalid JSon : Expected Array of objects\n";
                }
            }
        }
        $values = $settings['project-values'];
        foreach ($settings['project-values-type'] as $key => $valueType) {
            if ($valueType == 'json') {
                // check json is valid
                $rawValue = $values[$key];
                $list = json_decode($rawValue);
                if (is_null($list)) {
                    $errors .= "Invalid JSon [" . $projectCategory[$key] . "] : " . json_last_error_msg() . "\n";
                } else if (!is_array($list)) {
                    $errors .= "Invalid JSon : Expected Array of objects\n";
                }
            }
        }

        $siteCNRCode = $settings['site-no-result-code'];
        $siteCNRLabel = $settings['site-no-result-label'];

        foreach ($settings['site-return-no-result'] as $key => $returnNoResult) {
            if ($returnNoResult) {
                // check we have a code and label
                $label = trim($siteCNRLabel[$key]);
                $code = trim($siteCNRCode[$key]);
                if ($label === '') {
                    $errors .= "No Result Label is required [" . $siteCategory[$key] . "]\n";
                } else if ($label != strip_tags($label)) {
                    $errors .= "No Results Label has illegal characters -[" . $siteCategory[$key] . "] " . $label . "\n";
                }
                if ($code === '') {
                    $errors .= "No Result Code is required [" . $siteCategory[$key] . "]\n";
                } else if ($code != strip_tags($code)
                    || strpos($code, "'") !== false
                    || strpos($code, '"') !== false
                ) {
                    $errors .= "No Results Code has illegal characters [" . $siteCategory[$key] . "]- " . $code . "\n";
                }
            }
        }

        $projectCNRCode = $settings['project-no-result-code'];
        $projectCNRLabel = $settings['project-no-result-label'];

        foreach ($settings['project-return-no-result'] as $key => $returnNoResult) {
            if ($returnNoResult) {
                // check we have a code and label
                $label = trim($projectCNRLabel[$key]);
                $code = trim($projectCNRCode[$key]);
                if ($label === '') {
                    $errors .= "No Result Label is required [" . $projectCategory[$key] . "]\n";
                } else if ($label != strip_tags($label)) {
                    $errors .= "No Results Label has illegal characters [" . $projectCategory[$key] . "]- " . $label . "\n";
                }

                if ($code === '') {
                    $errors .= "No Result Code is required [" . $projectCategory[$key] . "]\n";
                } else if ($code != strip_tags($code)
                    || strpos($code, "'") !== false
                    || strpos($code, '"') !== false
                ) {
                    $errors .= "No Results Code has illegal characters [" . $projectCategory[$key] . "]- " . $code . "\n";
                }
            }
        }


        return $errors;
    }

    /**
     * return the name of the ontology service as it will be display on the service selection
     * drop down.
     */
    public function getProviderName()
    {
        return 'Site Defined Ontologies';
    }


    /**
     * return the prefex used to denote ontologies provided by this provider.
     */
    public function getServicePrefix()
    {
        return 'SIMPLE';
    }

    function getSystemCategories()
    {
        $key = 'site-category-list';
        $keys = ['site-category' => 'category',
            'site-name' => 'name',
            'site-search-type' => 'search-type',
            'site-return-no-result' => 'return-no-result',
            'site-no-result-label' => 'no-result-label',
            'site-no-result-code' => 'no-result-code',
            'site-values-type' => 'values-type',
            'site-values' => 'values'];
        $subSettings = [];
        $rawSettings = $this->getSubSettings($key);
        //error_log("system_settings = ".print_r($rawSettings, TRUE));
        foreach ($rawSettings as $data) {
            $subSetting = [];
            foreach ($keys as $k => $nk) {
                $subSetting[$nk] = $data[$k];
            }
            $subSettings[] = $subSetting;
        }
        return $subSettings;
    }

    /**
     * @param mixed $projectId Defaults to the current project context. Pass
     *   explicitly to read another project's category list (needed by the
     *   control-center cache refresh, which has to check per-project
     *   overrides without switching page context - see design.md Decision 3).
     */
    function getProjectCategories($projectId = null)
    {
        $key = 'project-category-list';
        $keys = ['project-category' => 'category',
            'project-name' => 'name',
            'project-search-type' => 'search-type',
            'project-return-no-result' => 'return-no-result',
            'project-no-result-label' => 'no-result-label',
            'project-no-result-code' => 'no-result-code',
            'project-values-type' => 'values-type',
            'project-values' => 'values'];
        $subSettings = [];
        $rawSettings = $this->getSubSettings($key, $projectId);
        //error_log("project_settings = ".print_r($rawSettings, TRUE));
        foreach ($rawSettings as $data) {
            $subSetting = [];
            foreach ($keys as $k => $nk) {
                $subSetting[$nk] = $data[$k];
            }
            $subSettings[] = $subSetting;
        }
        return $subSettings;
    }

    /**
     * Return a string which will be placed in the online designer for
     * selecting an ontology for the service.
     * When an ontology is selected it should make a javascript call to
     * update_ontology_selection($service, $category)
     *
     * The provider may include a javascript function
     * <service>_ontology_changed(service, category)
     * which will be called when the ontology selection is changed. This function
     * would update any UI elements is the service matches or clear the UI elemements
     * if they do not.
     */
    public function getOnlineDesignerSection()
    {
        $systemCategories = $this->getSystemCategories();
        $projectCategories = $this->getProjectCategories();

        $categories = [];
        foreach ($systemCategories as $cat) {
            $categories[$cat['category']] = $cat;
        }
        foreach ($projectCategories as $cat) {
            $categories[$cat['category']] = $cat;
        }

        $categoryList = '';
        foreach ($categories as $cat) {
            $category = $cat['category'];
            $name = $cat['name'];
            $categoryList .= "<option value='{$category}'>{$name}</option>\n";
        }

        $onlineDesignerJsUrl = $this->getUrl('js/online-designer.js');
        $onlineDesignerHtml = <<<EOD
<script src="{$onlineDesignerJsUrl}"></script>
<div style='margin-bottom:3px;'>
  Select Local Ontology to use:
</div>
<select id='simple_ontology_category' name='simple_ontology_category' 
            onchange="update_ontology_selection('SIMPLE', this.options[this.selectedIndex].value)"
            class='x-form-text x-form-field' style='width:330px;max-width:330px;'>
        {$categoryList}
</select>
EOD;
        return $onlineDesignerHtml;
    }

    /**
     * Search API with a search term for a given ontology
     * Returns array of results with Notation as key and PrefLabel as value.
     */
    public function searchOntology($category, $search_term, $result_limit)
    {
        $systemCategories = $this->getSystemCategories();
        $projectCategories = $this->getProjectCategories();
        $hideChoice = $this->getHideChoice();
        $categories = [];
        foreach ($systemCategories as $cat) {
            $categories[$cat['category']] = $cat;
        }
        foreach ($projectCategories as $cat) {
            $categories[$cat['category']] = $cat;
        }

        $categoryData = isset($categories[$category]) ? $categories[$category] : null;
        $values = $categoryData ? $this->parseCategoryValues($categoryData) : array();
        //error_log(print_r($values, TRUE));
        $wordResults = array();
        $strippedSearchTerm = $this->skip_accents($search_term);
        if ($categoryData && $categoryData['search-type'] == 'full') {
            $searchWords = [$strippedSearchTerm];
        } else {
            if (strlen($strippedSearchTerm) > 0 && ($strippedSearchTerm[0] == "'" || $strippedSearchTerm[0] == '"')) {
                $searchWords = [substr($strippedSearchTerm, 1)];
            } else {
                $searchWords = explode(' ', $strippedSearchTerm);
            }
        }

        foreach ($values as $val) {
            if ($val['active'] === false) {
                // marked as inactive
                continue;
            }
            $code = $val['code'];
            if (in_array($code, $hideChoice)){
                // in hide choice list
                continue;
            }
            $desc = $val['display'];
            $synonyms = $val['synonyms'];
            $strippedDesc = $this->skip_accents($desc);
            $foundCount = 0;
            $minPos = 99;
            foreach ($searchWords as $word) {
                $pos = stripos($strippedDesc, $word);
                if ($pos !== FALSE) {
                    $foundCount++;
                    if ($pos < $minPos) {
                        $minPos = $pos;
                    }
                }
            }
            if ($synonyms) {
                foreach ($synonyms as $synonym) {
                    $synonymStrippedDesc = $this->skip_accents($synonym);
                    $synonymFoundCount = 0;
                    $synonymMinPos = 99;
                    foreach ($searchWords as $word) {
                        $synonymPos = stripos($synonymStrippedDesc, $word);
                        if ($synonymPos !== FALSE) {
                            $synonymFoundCount++;
                            if ($synonymPos < $synonymMinPos) {
                                $synonymMinPos = $synonymPos;
                            }
                        }
                    }
                    if ($synonymFoundCount > $foundCount) {
                        $foundCount = $synonymFoundCount;
                        $minPos = $synonymMinPos;
                    } else if ($synonymFoundCount == $foundCount && $synonymMinPos < $minPos) {
                        $minPos = $synonymMinPos;
                    }
                }
            }
            if ($foundCount > 0) {
                $wordResults[] = array('foundCount' => $foundCount, 'minPos' => $minPos, 'value' => $val);
            }
        }
        $fcColumn = array_column($wordResults, 'foundCount');
        $posColumn = array_column($wordResults, 'minPos');

        // sort on word match count then on closest to start of string
        array_multisort($fcColumn, SORT_DESC, $posColumn, SORT_ASC, $wordResults);
        $mresults = array_column($wordResults, 'value');

        $results = array();
        foreach ($mresults as $val) {
            // Returned raw, matching REDCap core's own BioPortalOntologyProvider:
            // DataEntry/web_service_auto_suggest.php only reverses HTML-escaping
            // (label_decode()) on the label, never on the value/code, so an
            // escaped code here would be saved into the record verbatim (e.g.
            // "Child&#039;s Nervous System" instead of "Child's Nervous
            // System") - see GitHub issue #5. label_decode() + filter_tags()
            // in core remain the actual sanitization boundary for the label.
            $results[$val['code']] = $val['display'];
        }

        $result_limit = (is_numeric($result_limit) ? $result_limit : 20);

        if ($categoryData && count($results) < $result_limit) {
            // add no results found
            $return_no_result = $categoryData['return-no-result'];
            if ($return_no_result) {
                $no_result_label = $categoryData['no-result-label'];
                $no_result_code = $categoryData['no-result-code'];
                $results[$no_result_code] = $no_result_label;
            }
        }

        // Return array of results
        return array_slice($results, 0, $result_limit, true);
    }


    /**
     *  Takes the value and gives back the label for the value.
     *
     * @param mixed $projectId Defaults to the current project context; pass
     *   explicitly to resolve the label as it would appear in another
     *   project (used by cache-refresh scope resolution).
     */
    public function getLabelForValue($category, $value, $projectId = null)
    {
        // $values used to be built independently here as a list of
        // ['code' => .., 'display' => ..] pairs (missing the parsing this
        // function's sibling searchOntology() otherwise shared), and
        // looked up with array_key_exists($value, $values) - which only
        // ever matches numeric list indices, never a code string, so the
        // lookup always fell through to returning the raw code unchanged.
        // Sharing parseCategoryValues() with searchOntology() means an
        // entry previously marked inactive (a leading '!'/'\!', still
        // resolvable per README's "Active flag" section since a record
        // may already hold that value from before it was deactivated)
        // parses to the same code here as it does there.
        $labelsByCode = $this->labelMapForCategory($category, $projectId);
        if (array_key_exists($value, $labelsByCode)) {
            return $labelsByCode[$value];
        }
        return $value;
    }

    /**
     * Merge system and project (project-overrides-system) category
     * definitions for $category, as visible in $projectId (defaults to the
     * current project context), into a code => current display map.
     */
    private function labelMapForCategory($category, $projectId = null)
    {
        $categories = [];
        foreach ($this->getSystemCategories() as $cat) {
            $categories[$cat['category']] = $cat;
        }
        foreach ($this->getProjectCategories($projectId) as $cat) {
            $categories[$cat['category']] = $cat;
        }

        $categoryData = isset($categories[$category]) ? $categories[$category] : null;
        return $categoryData ? $this->labelMapFor($categoryData) : [];
    }

    /**
     * Parse a single category definition into a code => current display map,
     * with no system/project merging (used for the control-center cache
     * refresh, which must resolve strictly against the system definition -
     * see design.md Decision 3).
     */
    private function labelMapFor($categoryData)
    {
        $map = [];
        foreach ($this->parseCategoryValues($categoryData) as $item) {
            $map[$item['code']] = $item['display'];
        }
        return $map;
    }

    /**
     * Parse a category's raw 'values' config into a list of
     * ['code' => .., 'display' => .., 'active' => .., 'synonyms' => ..]
     * entries, regardless of 'values-type'. Shared by searchOntology() and
     * getLabelForValue() so the '!'/'\!' inactive-marker handling (and the
     * documented optional fields for 'json') can't drift between the two.
     */
    private function parseCategoryValues($categoryData)
    {
        $values = array();
        $type = $categoryData['values-type'];
        $rawValues = $categoryData['values'];

        if ($type == 'list') {
            $list = preg_split("/\r\n|\n|\r/", $rawValues);
            foreach ($list as $item) {
                $active = true;
                if (strncmp($item, "\\!", 2) === 0) {
                    // \! escaped !
                    $item = substr($item, 1); // remove leading \
                } else if (strncmp($item, "!", 1) === 0) {
                    // not active
                    $item = substr($item, 1);  // remove leading !
                    $active = false;
                }
                $values[] = ['code' => $item, 'display' => $item, 'active' => $active, 'synonyms' => []];
            }
        } elseif ($type == 'bar') {
            $rows = preg_split("/\r\n|\n|\r/", $rawValues);
            foreach ($rows as $row) {
                $cols = explode('|', $row);
                $col_rev = array_reverse($cols);
                $code = array_pop($col_rev);
                $active = true;
                if (strncmp($code, "\\!", 2) === 0) {
                    // \! escaped !
                    $code = substr($code, 1); // remove leading \
                } else if (strncmp($code, "!", 1) === 0) {
                    // not active
                    $code = substr($code, 1);  // remove leading !
                    $active = false;
                }
                $display = array_pop($col_rev);
                $values[] = [
                    'code' => $code,
                    // a row without a '|' has nothing left after popping the
                    // code - fall back to the code itself rather than null
                    'display' => $display !== null ? $display : $code,
                    'active' => $active,
                    'synonyms' => $col_rev,
                ];
            }
        } elseif ($type == 'json') {
            $list = json_decode($rawValues, true);
            if (is_array($list)) {
                foreach ($list as $item) {
                    if (isset($item['code']) and isset($item['display'])) {
                        // 'active' and 'synonyms' are documented as optional
                        $values[] = [
                            'code' => $item['code'],
                            'display' => $item['display'],
                            'active' => isset($item['active']) ? $item['active'] : true,
                            'synonyms' => isset($item['synonyms']) ? $item['synonyms'] : [],
                        ];
                    }
                }
            }
        }
        return $values;
    }

    /*
     * Function taken from Blog posting :
     *
     *   Fonction PHP pour supprimer les accents - Murviel Info
     *   https://murviel-info-beziers.com/fonction-php-supprimer-accents/
     */
    function skip_accents($str, $charset = 'utf-8')
    {

        $str = htmlentities($str, ENT_NOQUOTES, $charset);

        $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
        $str = preg_replace('#&[^;]+;#', '', $str);

        return $str;
    }

    function getHideChoice()
    {
        global $Proj;
        $codesToHide=[];
        $annotations = null;
        if (isset($_GET['field'])){
            $field = $_GET['field'];
            if (isset($Proj->metadata[$field])) {
                $annotations = $Proj->metadata[$field]['field_annotation'];
            }
            else if (isset($_GET['pid'])){
                $project_id = $_GET['pid'];
                $dd_array = \REDCap::getDataDictionary($project_id, 'array', false, array($field));
                $annotations = isset($dd_array[$field]) ? $dd_array[$field]['field_annotation'] : null;
            }
            if ($annotations) {
                $offset = 0;
                while (preg_match("/@HIDECHOICE='([^']*)'/", $annotations, $matches, PREG_OFFSET_CAPTURE, $offset) === 1){
                    $listedCodesStr = $matches[1][0];
                    $listedCodes = explode(',', $listedCodesStr);
                    foreach($listedCodes as $code){
                        array_push($codesToHide, trim($code));
                    }
                    $offset = $matches[0][1] + strlen($matches[0][0]);
                }
            }
        }

        return $codesToHide;
    }

    // --- Ontology cache refresh (aehrc/redcap_simple_ontology_provider#10) ---
    //
    // redcap_web_service_cache (project_id, service, category, value, label) is
    // populated the first time a project resolves a code and read from then on
    // instead of calling this module again, so editing a category's values
    // doesn't reach records that already cached the old display. The methods
    // below let a project link and a control-center link preview and apply
    // corrections. See openspec/changes/simple-ontology-cache-refresh/design.md
    // for the full rationale behind the scope split and the shadow-check.

    /**
     * Cached (project_id, value, label) rows for this module's service under
     * $category. $projectId narrows to one project; null means every project
     * that has ever cached a value for this category.
     *
     * @return list<array{project_id: mixed, value: string, label: string}>
     */
    private function getCachedEntries($category, $projectId = null)
    {
        if ($projectId !== null) {
            $result = $this->query(
                "select project_id, value, label from redcap_web_service_cache
                 where service = ? and category = ? and project_id = ?",
                [$this->getServicePrefix(), $category, $projectId]
            );
        } else {
            $result = $this->query(
                "select project_id, value, label from redcap_web_service_cache
                 where service = ? and category = ?",
                [$this->getServicePrefix(), $category]
            );
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * The code => current-display map that should be used to judge whether a
     * cache row for ($category, $rowProjectId) is stale, for the given
     * refresh scope.
     *
     * - Project scope ($scopeProjectId set): the normal system-overridden-
     *   by-project merge, bound to that project - identical to what a record
     *   in that project actually resolves to.
     * - System scope ($scopeProjectId === null): the system category
     *   definition only. If $rowProjectId has its own project-level category
     *   of the same name, that project's cache belongs to the project-scope
     *   page instead, so null is returned to signal "skip this project".
     *
     * Returns null when there is nothing to resolve against (unknown
     * category, or a system-scope project that shadows it).
     */
    private function resolveCodeMapForScope($category, $scopeProjectId, $rowProjectId)
    {
        if ($scopeProjectId !== null) {
            return $this->labelMapForCategory($category, $rowProjectId);
        }

        foreach ($this->getProjectCategories($rowProjectId) as $projectCategory) {
            if ($projectCategory['category'] === $category) {
                return null;
            }
        }

        foreach ($this->getSystemCategories() as $systemCategory) {
            if ($systemCategory['category'] === $category) {
                return $this->labelMapFor($systemCategory);
            }
        }

        return null;
    }

    /**
     * Find cache rows for $category whose label no longer matches what the
     * category's active configuration would return today. A code with no
     * current match at all (removed/renamed) is left out entirely rather
     * than being reported as stale, since there is no correct new label to
     * offer for it.
     *
     * $projectId null means system/control-center scope (every project that
     * has cached a value for this category, except one that shadows it with
     * its own project-level category of the same name - see
     * resolveCodeMapForScope()). $projectId set means project scope (that
     * project's own cache rows only).
     *
     * @return array{
     *   stale: list<array{project_id: mixed, value: string, old_label: string, new_label: string}>,
     *   skippedProjects: list<array{project_id: mixed, reason: string}>
     * }
     */
    public function findStaleCacheEntries($category, $projectId = null)
    {
        $stale = [];
        $skippedProjects = [];

        if ($projectId === null) {
            $categoryExists = false;
            foreach ($this->getSystemCategories() as $systemCategory) {
                if ($systemCategory['category'] === $category) {
                    $categoryExists = true;
                    break;
                }
            }
            if (!$categoryExists) {
                return ['stale' => [], 'skippedProjects' => []];
            }
        }

        $rowsByProject = [];
        foreach ($this->getCachedEntries($category, $projectId) as $row) {
            $rowsByProject[$row['project_id']][] = $row;
        }

        foreach ($rowsByProject as $rowProjectId => $projectRows) {
            // Use each row's own project_id (not the array-grouping key,
            // which PHP silently casts to int for a numeric-string key) so
            // the returned project_id always matches what the DB actually holds.
            $codeMap = $this->resolveCodeMapForScope($category, $projectId, $rowProjectId);
            if ($codeMap === null) {
                if ($projectId === null) {
                    $skippedProjects[] = [
                        'project_id' => $projectRows[0]['project_id'],
                        'reason' => 'project defines its own category with this name',
                    ];
                }
                continue;
            }
            foreach ($projectRows as $row) {
                if (!array_key_exists($row['value'], $codeMap)) {
                    continue;
                }
                $newLabel = $codeMap[$row['value']];
                if ($newLabel !== $row['label']) {
                    $stale[] = [
                        'project_id' => $row['project_id'],
                        'value' => $row['value'],
                        'old_label' => $row['label'],
                        'new_label' => $newLabel,
                    ];
                }
            }
        }

        return ['stale' => $stale, 'skippedProjects' => $skippedProjects];
    }

    /**
     * Apply a user-confirmed subset of stale entries for $category. The new
     * label for each entry is re-derived right now (never trusted from
     * $confirmedEntries, which may be an outdated preview snapshot), and only
     * entries that are still actually stale as of this call are written.
     *
     * @param list<array{project_id: mixed, value: string}> $confirmedEntries
     * @return list<array{project_id: mixed, value: string, old_label: string, new_label: string}>
     */
    public function applyCacheRefresh($category, $projectId, array $confirmedEntries)
    {
        $valuesByProject = [];
        foreach ($confirmedEntries as $entry) {
            $valuesByProject[$entry['project_id']][] = $entry['value'];
        }

        $updated = [];
        foreach ($valuesByProject as $rowProjectId => $confirmedValues) {
            $codeMap = $this->resolveCodeMapForScope($category, $projectId, $rowProjectId);
            if ($codeMap === null) {
                // No longer resolvable against this scope as of right now
                // (e.g. the project has since defined its own override) -
                // skip rather than write a value the current scope can't justify.
                continue;
            }
            foreach ($this->getCachedEntries($category, $rowProjectId) as $row) {
                if (!in_array($row['value'], $confirmedValues, true)) {
                    continue;
                }
                if (!array_key_exists($row['value'], $codeMap)) {
                    continue;
                }
                $newLabel = $codeMap[$row['value']];
                if ($newLabel === $row['label']) {
                    continue;
                }
                $this->query(
                    "update redcap_web_service_cache set label = ?
                     where project_id = ? and service = ? and category = ? and value = ?",
                    [$newLabel, $row['project_id'], $this->getServicePrefix(), $category, $row['value']]
                );
                $this->log('Refreshed Simple Ontology cache entry', [
                    'category' => $category,
                    'value' => $row['value'],
                    'old_label' => $row['label'],
                    'new_label' => $newLabel,
                    'project_id' => $row['project_id'],
                ]);
                $updated[] = [
                    'project_id' => $row['project_id'],
                    'value' => $row['value'],
                    'old_label' => $row['label'],
                    'new_label' => $newLabel,
                ];
            }
        }

        $this->clearCacheRefreshPending($projectId, $category);

        return $updated;
    }

    // --- Save-time "you may need to refresh the cache" reminder ---

    /**
     * System categories, plus project categories only when there is an
     * actual project to fetch them for. getProjectCategories() is a
     * project-scoped settings lookup - the real framework throws ("The
     * Project Id cannot be null!") if it's called with no project context
     * at all, which a system-level (Control Center) settings save has none
     * of. getSystemCategories() has no such restriction.
     */
    private function allCurrentCategories($projectId = null)
    {
        $projectId = $projectId !== null ? $projectId : $this->getProjectId();
        $categories = $this->getSystemCategories();
        if ($projectId) {
            $categories = array_merge($categories, $this->getProjectCategories($projectId));
        }
        return $categories;
    }

    /**
     * The system-level kill switch for the entire cache-refresh mechanism
     * (see design.md Decision 6). Phrased as an opt-out setting
     * ('disable-cache-refresh') rather than an opt-in one, because
     * config.json's 'default' attribute for settings is documented as
     * unreliable, while an unchecked checkbox is REDCap's one dependable
     * default - so "never set" and "explicitly unchecked" both correctly
     * mean "enabled" here.
     */
    private function isCacheRefreshEnabled()
    {
        return !$this->getSystemSetting('disable-cache-refresh');
    }

    public function redcap_module_save_configuration($project_id)
    {
        if (!$this->isCacheRefreshEnabled()) {
            return;
        }

        $before = $this->categoriesBeforeSave ?? [];
        $changed = [];
        foreach ($this->allCurrentCategories($project_id) as $cat) {
            $previousValues = $before[$cat['category']] ?? null;
            if ($previousValues !== null && $previousValues !== $cat['values']) {
                $changed[] = $cat['category'];
            }
        }

        if (!empty($changed)) {
            $pending = $this->getCacheRefreshPending($project_id);
            $this->setCacheRefreshPending($project_id, array_merge($pending, $changed));
        }
    }

    public function redcap_module_configuration_settings($project_id, $settings)
    {
        if (!$this->isCacheRefreshEnabled()) {
            return $settings;
        }

        $pending = $this->getCacheRefreshPending($project_id);
        if (!empty($pending)) {
            $refreshPageUrl = $project_id
                ? $this->getUrl('pages/refresh_cache_project.php')
                : $this->getUrl('pages/refresh_cache_admin.php');
            $categoryList = implode(', ', $pending);
            array_unshift($settings, [
                'key' => 'cache-refresh-reminder',
                'name' => "The following ontology categories have changed values since they were last cached: "
                    . "<strong>{$categoryList}</strong>. Existing records may still show the old display text "
                    . "until the cache is refreshed. <a href='{$refreshPageUrl}' target='_blank'>Open the cache refresh page</a>.",
                'type' => 'descriptive',
            ]);
        }
        return $settings;
    }

    /**
     * Category names flagged as possibly needing a cache refresh for the
     * given scope (project id, or null for system scope) - read by the
     * refresh pages to pre-select/highlight a flagged category. Public: the
     * module pages that render the category picker call this directly.
     */
    public function getCacheRefreshPending($projectId)
    {
        $raw = $projectId ? $this->getProjectSetting('cache-refresh-pending') : $this->getSystemSetting('cache-refresh-pending');
        $list = json_decode((string)$raw, true);
        return is_array($list) ? $list : [];
    }

    private function setCacheRefreshPending($projectId, array $categories)
    {
        $categories = array_values(array_unique($categories));
        $value = empty($categories) ? null : json_encode($categories);
        if ($projectId) {
            $this->setProjectSetting('cache-refresh-pending', $value);
        } else {
            $this->setSystemSetting('cache-refresh-pending', $value);
        }
    }

    private function clearCacheRefreshPending($projectId, $category)
    {
        $pending = $this->getCacheRefreshPending($projectId);
        $this->setCacheRefreshPending($projectId, array_values(array_diff($pending, [$category])));
    }

    // --- Module page access and ajax wiring ---

    /**
     * Project links default to REDCap's general Project Design right (see
     * AbstractExternalModule::redcap_module_link_check_display()); the
     * refresh-cache-project link should instead follow this module's own
     * per-user configuration right, since refreshing the cache is a direct
     * consequence of editing the same category values that right already
     * permits (see design.md Decision 4). This hook also gates direct
     * navigation to the page URL, not just the nav link's visibility.
     */
    public function redcap_module_link_check_display($project_id, $link)
    {
        $linkKey = $link['key'] ?? null;
        if (in_array($linkKey, ['refresh-cache-project', 'refresh-cache-admin'], true) && !$this->isCacheRefreshEnabled()) {
            return null;
        }

        if ($linkKey === 'refresh-cache-project') {
            return ExternalModules::hasModuleConfigurationUserRights($this->PREFIX) ? $link : null;
        }
        return parent::redcap_module_link_check_display($project_id, $link);
    }

    /**
     * Ajax requests don't route through redcap_module_link_check_display(),
     * so permission is re-checked here independently of whichever page the
     * request came from.
     */
    private function currentUserMayRefreshCache($project_id)
    {
        if ($project_id) {
            return ExternalModules::hasModuleConfigurationUserRights($this->PREFIX);
        }
        return $this->isSuperUser();
    }

    private function categoryIsValidForScope($category, $project_id)
    {
        if (!is_string($category) || $category === '') {
            return false;
        }
        $categories = $project_id ? $this->getProjectCategories($project_id) : $this->getSystemCategories();
        foreach ($categories as $cat) {
            if ($cat['category'] === $category) {
                return true;
            }
        }
        return false;
    }

    public function redcap_module_ajax($action, $payload, $project_id)
    {
        if (!$this->isCacheRefreshEnabled()) {
            return ['error' => 'Ontology cache refresh has been disabled by the REDCap administrator.'];
        }

        if (!$this->currentUserMayRefreshCache($project_id)) {
            return ['error' => 'You are not authorized to refresh the ontology cache.'];
        }

        $category = is_array($payload) ? ($payload['category'] ?? null) : null;
        if (!$this->categoryIsValidForScope($category, $project_id)) {
            return ['error' => 'Unknown category.'];
        }

        if ($action === 'preview-cache-refresh') {
            $result = $this->findStaleCacheEntries($category, $project_id ?: null);
            return [
                'category' => $category,
                'stale' => $result['stale'],
                'skippedProjects' => $result['skippedProjects'],
            ];
        }

        if ($action === 'apply-cache-refresh') {
            $entries = is_array($payload['entries'] ?? null) ? $payload['entries'] : [];
            if ($project_id) {
                // Ignore anything the client claims about another project -
                // the project-scope action may only ever touch its own project_id.
                $entries = array_values(array_filter($entries, function ($entry) use ($project_id) {
                    return isset($entry['project_id']) && (string)$entry['project_id'] === (string)$project_id;
                }));
            }
            $updated = $this->applyCacheRefresh($category, $project_id ?: null, $entries);
            return ['category' => $category, 'updated' => $updated];
        }

        return ['error' => 'Unknown action.'];
    }
}
