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
     * ajaxAction for giving the user realtime info when the user is editing a product or category.
     * @return Json array|null
     */
    public function ajaxAction()
    {
        //Obtain the type, the reference id and the post data.
        $type = $this->getRequest()->getParam('type');
        $referenceId = $this->getRequest()->getParam('reference');
        $data = $this->getRequest()->getPost();

        //Remove the form_key from the post data
        unset($data['form_key']);

        try
        {
            //Call the helper to execute an API request to Reload.
            $response = Mage::helper('reload_seo')->ajaxCheck($type, $referenceId, $data);
        }
        catch(Exception $ex)
        {
            //Something went wrong, result null.
            $response = null;
        }
        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json', true);
        $this->getResponse()->setBody(json_encode($response));
    }

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
            Mage::helper('reload_seo')->updateProducts($this->getRequest()->getParam('product'));

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