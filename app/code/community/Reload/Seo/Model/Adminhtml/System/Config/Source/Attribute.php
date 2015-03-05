<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Reload_Seo_Model_Adminhtml_System_Config_Source_Attribute
{
    /**
     * toOptionArray searches all product attributes and returns them in an array.
     * 
     * @return array
     */
    public function toOptionArray()
    {
        //Get the attribute collection with visible and boolean attributes.
        $attributeCollection = Mage::getResourceModel('catalog/product_attribute_collection')->addFilter('is_visible', 1);

        //Get the attribute code for all and sort them.
        $attributes = $attributeCollection->getColumnValues('attribute_code');
        natsort($attributes);

        //Create the options array and add an empty option.
        $options = array();
        $options[] = array(
            'value' => '',
            'label' => ''
        );

        //Loop over the attributes and create the options.
        foreach ($attributes as $_attributeCode) {
            $options[] = array(
                'value' => $_attributeCode,
                'label' => $_attributeCode
            );
        }

        return $options;
    }
}
