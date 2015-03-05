<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Reload_Seo_Model_Resource_Score extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('reload_seo/score', 'id');
    }
}