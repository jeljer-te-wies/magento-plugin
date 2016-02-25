<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Reload_Seo_Adminhtml_SeoController handles the mass update and ajax actions.
 */
class Reload_Seo_Adminhtml_SeoController extends Mage_Adminhtml_Controller_Action
{ 
    /**
     * updateproductsAction is used to handle the mass action in the products grid.
     * 
     * @return Redirect adminhtml/catalog_product/index
     */
    public function updateproductsAction()
    {
        try
        {
            //Call the helper to update the products with the given ids.
            Mage::helper('reload_seo/massaction')->updateProducts($this->getRequest()->getParam('product'));

            //Set the success message.
            Mage::getSingleton('adminhtml/session')->addSuccess('The SEO statusses have been updated.');
        }
        catch(Exception $ex)
        {
            //Something went wrong, set the error message.
            Mage::getSingleton('adminhtml/session')->addError($ex->getMessage());
        }
        return $this->_redirect('adminhtml/catalog_product/index');
    }
}