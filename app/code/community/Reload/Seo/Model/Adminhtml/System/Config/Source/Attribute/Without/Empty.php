<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Reload_Seo_Model_Adminhtml_System_Config_Source_Attribute_Without_Empty extends Reload_Seo_Model_Adminhtml_System_Config_Source_Attribute
{
    /**
     * toOptionArray searches all product attributes and returns them in an array without an empty option.
     * 
     * @return array
     */
    public function toOptionArray()
    {
        $options = parent::toOptionArray();
        if(array_key_exists(0, $options))
        {
            unset($options[0]);
        }
        return $options;
    }
}
