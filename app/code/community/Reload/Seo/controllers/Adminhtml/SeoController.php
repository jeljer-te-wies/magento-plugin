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

    /**
     * saveResultAction is used to update the score objects when the score was calculated with ajax.
     * 
     * @return Json
     */
    public function saveResultAction()
    {
        //Get the post data and the score update request key.
        $post = Mage::app()->getRequest()->getPost();
        $requestKey = Mage::app()->getRequest()->getParam('request_key');

        //Obtain and remove the request so it won't be handled again.
        $updateRequest = Mage::helper('reload_seo')->removeScoreUpdateRequest($requestKey);

        if($updateRequest == null || !array_key_exists('score', $post))
        {
            //The request key does not exist, just ignore it.
            $result = null;
        }
        else
        {
            //Get the score from the post.
            $score = $post['score'];
            if($score === null || !array_key_exists('score', $score))
            {
                //No score was found.
                $result = $this->__('Something went wrong while updating the ' . $updateRequest['type'] . ' SEO status.');
            }
            else
            {
                //Obtain the score for merging.
                $scoreObject = Mage::getModel('reload_seo/score')->loadById($updateRequest['id'], $updateRequest['type']);

                //Merge the result in the score object.
                $scoreObject->mergeFromResult($score);

                $result = null;
            }
        }

        //Set the result as response.
        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json', true);
        $this->getResponse()->setBody(json_encode($result));
    }
}