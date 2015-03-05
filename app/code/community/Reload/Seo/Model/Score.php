<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Reload_Seo_Model_Score is the model for storing the score results with.
 */
class Reload_Seo_Model_Score extends Mage_Core_Model_Abstract
{    
    /**
     * _construct basic magento constructor
     * @return void
     */
    public function _construct()
    {
        $this->_init('reload_seo/score');
    }

    /**
     * getRulesCollection returns the rules collection which are linked to this score object.
     * 
     * @return Reload_Seo_Model_Resource_Scores_Rule_Collection
     */
    public function getRulesCollection()
    {
    	return Mage::getModel('reload_seo/scores_rule')
            ->getCollection()
            ->addFieldToFilter('score_id', array('eq' => $this->getId()));
    }

    /**
     * loadById loads an score object by the given reference id and the type.
     * 
     * @param  string $id
     * @param  string $type
     * @return Reload_Seo_Model_Score
     */
    public function loadById($id, $type)
    {
        //If id === null, we want to search the record with reference id 0
        if($id === null)
        {
            $id = 0;
        }

        //Search the collection for items with the type and the reference id and select the first result.
        $score = $this->getCollection()
            ->addFieldToFilter('type', array('eq' => $type))
            ->addFieldToFilter('reference_id', array('eq' => $id))
            ->getFirstItem();

        if($score === null)
        {
            //No score found, create a new one.
            $score = Mage::getModel('reload_seo/score');
        }

        //Set the type and reference id for the case when the score object does not exist yet.
        $score->setType($type);
        $score->setReferenceId($id);

        if($id == null)
        {
            $score->setKeywords('');
        }

        return $score;
    }

    /**
     * mergeFromResult merges an Reload API result with this score item.
     * 
     * @param  array $result
     * @return void
     */
    public function mergeFromResult($result)
    {
        //Update this object with the score and color and save this object.
        $this->setScore($result['score']);
        $this->setColor($result['color']);
        $this->save();

        foreach($this->getRulesCollection() as $rule)
        {
            //Obtain all the rules of this score object and delete it.
            $rule->delete();
        }

        foreach($result['rules'] as $ruleResult)
        {
            //Loop over the rules in the result and bind them to this score object.
            $rule = Mage::getModel('reload_seo/scores_rule');
            $rule->setScoreId($this->getId());
            $rule->setField($ruleResult['field']);
            $rule->setTitle($ruleResult['title']);
            $rule->setStatus($ruleResult['color']);
            $rule->save();
        }
    }

    public function generateKeywords($name)
    {
        $exploded = explode(' ', $name);
        $unique = array();
        foreach($exploded as $explode)
        {
            $explode = str_replace(' ', '', $explode);
            if($explode != null)
            {
                $unique[strtolower($explode)] = strtolower($explode);
            }
        }
        $this->setKeywords(implode(',', array_values($unique)));
    }
}