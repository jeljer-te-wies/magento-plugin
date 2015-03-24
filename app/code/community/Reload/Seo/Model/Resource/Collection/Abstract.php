<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
if(class_exists('Mage_Core_Model_Resource_Db_Collection_Abstract'))
{
	class Reload_Seo_Model_Resource_Collection_Abstract extends Mage_Core_Model_Resource_Db_Collection_Abstract
    {

    }
}
else
{
	class Reload_Seo_Model_Resource_Collection_Abstract extends Varien_Data_Collection_Db
    {
      	
    }
}