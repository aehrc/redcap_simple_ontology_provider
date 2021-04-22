<?php
/**
 *
CSIRO Open Source Software Licence Agreement (variation of the BSD / MIT License)
Copyright (c) 2018, Commonwealth Scientific and Industrial Research Organisation (CSIRO) ABN 41 687 119 230.
All rights reserved. CSIRO is willing to grant you a licence to this SimpleOntologyExternalModule on the following terms, except where otherwise indicated for third party material.
Redistribution and use of this software in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
* Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
* Neither the name of CSIRO nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission of CSIRO.
EXCEPT AS EXPRESSLY STATED IN THIS AGREEMENT AND TO THE FULL EXTENT PERMITTED BY APPLICABLE LAW, THE SOFTWARE IS PROVIDED "AS-IS". CSIRO MAKES NO REPRESENTATIONS, WARRANTIES OR CONDITIONS OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO ANY REPRESENTATIONS, WARRANTIES OR CONDITIONS REGARDING THE CONTENTS OR ACCURACY OF THE SOFTWARE, OR OF TITLE, MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, NON-INFRINGEMENT, THE ABSENCE OF LATENT OR OTHER DEFECTS, OR THE PRESENCE OR ABSENCE OF ERRORS, WHETHER OR NOT DISCOVERABLE.
TO THE FULL EXTENT PERMITTED BY APPLICABLE LAW, IN NO EVENT SHALL CSIRO BE LIABLE ON ANY LEGAL THEORY (INCLUDING, WITHOUT LIMITATION, IN AN ACTION FOR BREACH OF CONTRACT, NEGLIGENCE OR OTHERWISE) FOR ANY CLAIM, LOSS, DAMAGES OR OTHER LIABILITY HOWSOEVER INCURRED.  WITHOUT LIMITING THE SCOPE OF THE PREVIOUS SENTENCE THE EXCLUSION OF LIABILITY SHALL INCLUDE: LOSS OF PRODUCTION OR OPERATION TIME, LOSS, DAMAGE OR CORRUPTION OF DATA OR RECORDS; OR LOSS OF ANTICIPATED SAVINGS, OPPORTUNITY, REVENUE, PROFIT OR GOODWILL, OR OTHER ECONOMIC LOSS; OR ANY SPECIAL, INCIDENTAL, INDIRECT, CONSEQUENTIAL, PUNITIVE OR EXEMPLARY DAMAGES, ARISING OUT OF OR IN CONNECTION WITH THIS AGREEMENT, ACCESS OF THE SOFTWARE OR ANY OTHER DEALINGS WITH THE SOFTWARE, EVEN IF CSIRO HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH CLAIM, LOSS, DAMAGES OR OTHER LIABILITY.
APPLICABLE LEGISLATION SUCH AS THE AUSTRALIAN CONSUMER LAW MAY APPLY REPRESENTATIONS, WARRANTIES, OR CONDITIONS, OR IMPOSES OBLIGATIONS OR LIABILITY ON CSIRO THAT CANNOT BE EXCLUDED, RESTRICTED OR MODIFIED TO THE FULL EXTENT SET OUT IN THE EXPRESS TERMS OF THIS CLAUSE ABOVE "CONSUMER GUARANTEES".  TO THE EXTENT THAT SUCH CONSUMER GUARANTEES CONTINUE TO APPLY, THEN TO THE FULL EXTENT PERMITTED BY THE APPLICABLE LEGISLATION, THE LIABILITY OF CSIRO UNDER THE RELEVANT CONSUMER GUARANTEE IS LIMITED (WHERE PERMITTED AT CSIRO'S OPTION) TO ONE OF FOLLOWING REMEDIES OR SUBSTANTIALLY EQUIVALENT REMEDIES:
(a)               THE REPLACEMENT OF THE SOFTWARE, THE SUPPLY OF EQUIVALENT SOFTWARE, OR SUPPLYING RELEVANT SERVICES AGAIN;
(b)               THE REPAIR OF THE SOFTWARE;
(c)               THE PAYMENT OF THE COST OF REPLACING THE SOFTWARE, OF ACQUIRING EQUIVALENT SOFTWARE, HAVING THE RELEVANT SERVICES SUPPLIED AGAIN, OR HAVING THE SOFTWARE REPAIRED.
IN THIS CLAUSE, CSIRO INCLUDES ANY THIRD PARTY AUTHOR OR OWNER OF ANY PART OF THE SOFTWARE OR MATERIAL DISTRIBUTED WITH IT.  CSIRO MAY ENFORCE ANY RIGHTS ON BEHALF OF THE RELEVANT THIRD PARTY.
Third Party Components
The following third party components are distributed with the Software.  You agree to comply with the licence terms for these components as part of accessing the Software.  Other third party software may also be identified in separate files distributed with the Software.
 * 
 * 
 * 
 */

namespace AEHRC\SimpleOntologyExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class SimpleOntologyExternalModule extends AbstractExternalModule  implements \OntologyProvider{

  public function __construct() {
      parent::__construct();
      // register with OntologyManager
      $manager = \OntologyManager::getOntologyManager();
      $manager->addProvider($this);
  }

  public function redcap_every_page_before_render ($project_id){
  }

  public function validateSettings($settings){
     $errors='';
    
     // make sure category has no markup or ' " char
     $siteCategory = $settings['site-category'];
     foreach($siteCategory as $category){
        if ($category != strip_tags($category) 
            || strpos($category, "'") !== false
            || strpos($category, '"') !== false
	){
          $errors .= "Category has illegal characters - ".$category."\n";
        }
      }
     $projectCategory = $settings['project-category'];
     foreach($projectCategory as $category){
        if ($category != strip_tags($category) 
             || strpos($category, "'") !== false
            || strpos($category, '"') !== false
             ){
          $errors .= "Category has illegal characters - ".$category."\n";
        }
      }
     // make sure name has no markup
     foreach($settings['site-name'] as $name){
        if ($name != strip_tags($name)){
          $errors .= "Name has illegal characters - ".$name."\n";
        }
      }
     foreach($settings['project-name'] as $name){
        if ($name != strip_tags($name)){
          $errors .= "Name has illegal characters - ".$name."\n";
        }
      }

     // If value is json make sure its valid json and is an array
     $values = $settings['site-values'];
     foreach($settings['site-values-type'] as $key=>$valueType){
        if ($valueType == 'json'){
          // check json is valid
          $rawValue = $values[$key];
		      $list = json_decode($rawValue);
          if (is_null($list)){
             $errors .= "Invalid JSon [".$siteCategory[$key]. "] : " .json_last_error_msg()."\n";
          }
          else if (!is_array($list)){
             $errors .= "Invalid JSon : Expected Array of objects\n";
          }
        }
      }
     $values = $settings['project-values'];
     foreach($settings['project-values-type'] as $key=>$valueType){
        if ($valueType == 'json'){
          // check json is valid
          $rawValue = $values[$key];
		      $list = json_decode($rawValue);
          if (is_null($list)){
             $errors .= "Invalid JSon [".$projectCategory[$key]. "] : " .json_last_error_msg()."\n";
          }
          else if (!is_array($list)){
             $errors .= "Invalid JSon : Expected Array of objects\n";
          }
        }
      }
      
      $siteCNRCode = $settings['site-no-result-code'];
      $siteCNRLabel = $settings['site-no-result-label'];
 
      foreach($settings['site-return-no-result'] as $key=>$returnNoResult){
          if ($returnNoResult){
              // check we have a code and label
              $label = trim($siteCNRLabel[$key]);
              $code = trim($siteCNRCode[$key]);
              if ($label === ''){
                  $errors .= "No Result Label is required [".$siteCategory[$key]. "]\n";
              }
              else if ($label != strip_tags($label)){
                  $errors .= "No Results Label has illegal characters -[".$siteCategory[$key]. "] ".$label."\n";
              }
              if ($code === ''){
                  $errors .= "No Result Code is required [".$siteCategory[$key]. "]\n";
              }
              else if ($code != strip_tags($code)
                  || strpos($code, "'") !== false
                  || strpos($code, '"') !== false
                  ){
                      $errors .= "No Results Code has illegal characters [".$siteCategory[$key]. "]- ".$code."\n";
              }
          }
      }
      
      $projectCNRCode = $settings['project-no-result-code'];
      $projectCNRLabel = $settings['project-no-result-label'];
      
      foreach($settings['project-return-no-result'] as $key=>$returnNoResult){
          if ($returnNoResult){
              // check we have a code and label
              $label = trim($projectCNRLabel[$key]);
              $code = trim($projectCNRCode[$key]);
              if ($label === ''){
                  $errors .= "No Result Label is required [".$projectCategory[$key]. "]\n";
              }
              else if ($label != strip_tags($label)){
                  $errors .= "No Results Label has illegal characters [".$projectCategory[$key]. "]- ".$label."\n";
              }
              
              if ($code === ''){
                  $errors .= "No Result Code is required [".$projectCategory[$key]. "]\n";
              }
              else if ($code != strip_tags($code)
                  || strpos($code, "'") !== false
                  || strpos($code, '"') !== false
                  ){
                      $errors .= "No Results Code has illegal characters [".$projectCategory[$key]. "]- ".$code."\n";
              }
          }
      }
      
      
     return $errors;
  }

  /**
    * return the name of the ontology service as it will be display on the service selection
    * drop down.
    */
  public function getProviderName(){
    return 'Site Defined Ontologies';
  }
    

  /**
    return the prefex used to denote ontologies provided by this provider.
   */
  public function getServicePrefix(){
    return 'SIMPLE';
  }

  function getSystemCategories() {
      $key = 'site-category-list';
      $keys = [ 'site-category' => 'category',
                'site-name' => 'name',
                'site-search-type' => 'search-type',
                'site-return-no-result' => 'return-no-result',
                'site-no-result-label' => 'no-result-label',
                'site-no-result-code' => 'no-result-code',
                'site-values-type' => 'values-type',
                'site-values' => 'values' ];
      $subSettings = [];
      $rawSettings = $this->getSubSettings($key);
      //error_log("system_settings = ".print_r($rawSettings, TRUE));
      foreach($rawSettings as $data){
        $subSetting = [];
        foreach($keys as $k=>$nk){
          $subSetting[$nk] = $data[$k];
        }
        $subSettings[] = $subSetting;
      }
      return $subSettings;
    }

  function getProjectCategories() {
      $key = 'project-category-list';
      $keys = [ 'project-category' => 'category',
                'project-name' => 'name',
                'project-search-type' => 'search-type',
                'project-return-no-result' => 'return-no-result',
                'project-no-result-label' => 'no-result-label',
                'project-no-result-code' => 'no-result-code',
                'project-values-type' => 'values-type',
                'project-values' => 'values' ];
      $subSettings = [];
      $rawSettings = $this->getSubSettings($key);
      //error_log("project_settings = ".print_r($rawSettings, TRUE));
      foreach($rawSettings as $data){
        $subSetting = [];
        foreach($keys as $k=>$nk){
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
  public function getOnlineDesignerSection(){
    $systemCategories = $this->getSystemCategories();
    $projectCategories = $this->getProjectCategories();

    $categories = [];
    foreach ($systemCategories as $cat){
      $categories[$cat['category']] = $cat;
    }
    foreach ($projectCategories as $cat){
      $categories[$cat['category']] = $cat;
    }

    $categoryList = '';
    foreach ($categories as $cat){
      $category = $cat['category'];
      $name = $cat['name'];
      $categoryList .= "<option value='{$category}'>{$name}</option>\n";
    }

   $onlineDesignerHtml = <<<EOD
<script type="text/javascript">
  function SIMPLE_ontology_changed(service, category){
    var newSelection = ('SIMPLE' == service) ? category : '';
    $('#simple_ontology_category').val(newSelection);
  }
  
</script>
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
	public function searchOntology($category, $search_term, $result_limit){
    $systemCategories = $this->getSystemCategories();
    $projectCategories = $this->getProjectCategories();
    $categories = [];
    foreach ($systemCategories as $cat){
      $categories[$cat['category']] = $cat;
    }
    foreach ($projectCategories as $cat){
      $categories[$cat['category']] = $cat;
    }

    $values = array();
    $categoryData = $categories[$category];
    if ($categoryData){
      $type = $categoryData['values-type'];
      $rawValues = $categoryData['values'];
      

      if ($type == 'list'){
        $list = preg_split("/\r\n|\n|\r/", $rawValues);
        foreach($list as $item){
          $values[] = ['code'=>$item,'display'=>$item];
        }
      }
      elseif ($type == 'bar'){
        $rows = preg_split("/\r\n|\n|\r/", $rawValues);
        foreach($rows as $row){
          $cols = explode('|', $row);
          $col_rev = array_reverse($cols);
          $values[] = ['code'=>array_pop($col_rev),'display'=>array_pop($col_rev), 'synonyms'=>$col_rev];
        }
      }
      elseif ($type == 'json'){
		     $list = json_decode($rawValues, true);
		     if (is_array($list)){
            foreach($list as $item){
              if (isset($item['code']) and isset($item['display'])){
                $values[] = ['code'=>$item['code'],'display'=>$item['display'], 'synonyms'=>$item['synonyms']];
              }
           }
        }
      }
    }
    //error_log(print_r($values, TRUE));
    $wordResults = array();
    $strippedSearchTerm = $this->skip_accents($search_term);
    if ($categoryData['search-type'] == 'full'){
        $searchWords = [$strippedSearchTerm];
    }
    else {
        if (strlen($strippedSearchTerm) > 0 && ($strippedSearchTerm[0] == "'" || $strippedSearchTerm[0] == '"')){
            $searchWords = [substr($strippedSearchTerm, 1)];
        }
        else {
            $searchWords = explode(' ', $strippedSearchTerm);
        }
    }

    foreach($values as $val){
      $code = $val['code'];
      $desc = $val['display'];
      $synonyms = $val['synonyms'];
      $strippedDesc = $this->skip_accents($desc);
      $foundCount=0;
      $minPos=99;
      foreach($searchWords as $word){
          $pos = stripos($strippedDesc, $word);
          if ($pos !== FALSE){
              $foundCount++;
              if ($pos < $minPos){
                  $minPos = $pos;
              }
          }
      }
      if ($synonyms){
          foreach($synonyms as $synonym){
              $synonymStrippedDesc = $this->skip_accents($synonym);
              $synonymFoundCount=0;
              $synonymMinPos=99;
              foreach($searchWords as $word){
                  $synonymPos = stripos($synonymStrippedDesc, $word);
                  if ($synonymPos !== FALSE){
                      $synonymFoundCount++;
                      if ($synonymPos < $synonymMinPos){
                          $synonymMinPos = $synonymPos;
                      }
                  }
              }
              if ($synonymFoundCount > $foundCount){
                  $foundCount = $synonymFoundCount;
                  $minPos = $synonymMinPos;
              }
              else if ($synonymFoundCount == $foundCount && $synonymMinPos < $minPos){
                  $minPos = $synonymMinPos;
              }
          }
      }
      if ($foundCount > 0){
          $wordResults[] = array( 'foundCount' => $foundCount, 'minPos' => $minPos, 'value' => $val);
      }
    }
    $fcColumn  = array_column($wordResults, 'foundCount');
    $posColumn  = array_column($wordResults, 'minPos');

    // sort on word match count then on closest to start of string
    array_multisort($fcColumn, SORT_DESC, $posColumn, SORT_ASC, $wordResults);
    $mresults = array_column($wordResults, 'value');
    
    $results = array();
    foreach($mresults as $val){
      // make sure result is escaped..
      $code = \REDCap::escapeHtml($val['code']);
      $desc = \REDCap::escapeHtml($val['display']);
      $results[$code] = $desc;
    }

    $result_limit = (is_numeric($result_limit) ? $result_limit : 20);

    if (count($results) < $result_limit) {
        // add no results found
        $return_no_result = $categoryData['return-no-result'];
        if ($return_no_result){
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
   */
  public function getLabelForValue($category, $value){
    $systemCategories = $this->getSystemCategories();
    $projectCategories = $this->getProjectCategories();
    $categories = [];
    foreach ($systemCategories as $cat){
      $categories[$cat['category']] = $cat;
    }
    foreach ($projectCategories as $cat){
      $categories[$cat['category']] = $cat;
    }

    $values = array();
    $categoryData = $categories[$category];
    if ($categoryData){
      $type = $categoryData['values-type'];
      $rawValues = $categoryData['values'];
      

      if ($type == 'list'){
        $list = preg_split("/\r\n|\n|\r/", $rawValues);
        foreach($list as $item){
          $values[] = ['code'=>$item,'display'=>$item];
        }
      }
      elseif ($type == 'bar'){
        $rows = preg_split("/\r\n|\n|\r/", $rawValues);
        foreach($rows as $row){
          $cols = explode('|', $row); 
          $values[] = ['code'=>$cols[0],'display'=>$cols[1]];
        }
      }
      elseif ($type == 'json'){
		     $list = json_decode($rawValues, true);
		     if (is_array($list)){
            foreach($list as $item){
              if (isset($item['code']) and isset($item['display'])){
                $values[] = ['code'=>$item['code'],'display'=>$item['display']];
              }
           }
        }
      }
      if (array_key_exists($value, $values)){
        return $values[$value];
      }
    }
    return $value;
  }

  /*
   * Function taken from Blog posting :
   *   
   *   Fonction PHP pour supprimer les accents - Murviel Info
   *   https://murviel-info-beziers.com/fonction-php-supprimer-accents/ 
   */
  function skip_accents( $str, $charset='utf-8' ) {
 
    $str = htmlentities( $str, ENT_NOQUOTES, $charset );
    
    $str = preg_replace( '#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str );
    $str = preg_replace( '#&([A-za-z]{2})(?:lig);#', '\1', $str );
    $str = preg_replace( '#&[^;]+;#', '', $str );
    
    return $str;
  }
  
}
