<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Reload_Seo_Helper_Data is the helper class for this module, mostly it contains functions
 * to handle the request with the Reload API.
 */
class Reload_Seo_Helper_Data extends Reload_Seo_Helper_Abstract
{
    /**
     * Variable for keeping track of the possible fields.
     * @var array
     */
    protected $fields = array(
        'description', 
        'short_description', 
        'meta_description', 
        'meta_keyword', 
        'meta_title', 
        'name',
        'url_key',
        'status',
    );

    /**
     * addScoreUpdateRequest adds a update request to the queue.
     * 
     * @param int       $id    The product or category id.
     * @param string    $type  Either product or category.
     * @param int       $store The store id.
     */
    public function addScoreUpdateRequest($id, $type, $store)
    {
        $requests = $this->getScoreUpdateRequests();

        $requests[$id . '-' . $type . '-' . $store] = array(
            'id' => $id,
            'type' => $type,
            'store' => $store
        );

        Mage::getSingleton('adminhtml/session')->setScoreUpdateRequests(json_encode($requests));
    }

    /**
     * removeScoreUpdateRequest removes a update request from the queue and returns it.
     * 
     * @param string $requestKey The request key.
     *
     * @return array The update request.
     */
    public function removeScoreUpdateRequest($requestKey)
    {
        $requests = $this->getScoreUpdateRequests();

        $request = null;
        if(array_key_exists($requestKey, $requests))
        {
            $request = $requests[$requestKey];
            unset($requests[$requestKey]);
        }

        Mage::getSingleton('adminhtml/session')->setScoreUpdateRequests(json_encode($requests));
        return $request;
    }

    /**
     * getScoreUpdateRequests gets the score update request queue.
     * 
     * @return array
     */
    public function getScoreUpdateRequests()
    {
        $session = Mage::getSingleton('adminhtml/session');

        $requests = json_decode($session->getScoreUpdateRequests(), true);
        if(!is_array($requests))
        {
            $requests = array();
        }

        return $requests;
    }

    /**
     * removeItem removes one product or category SEO status.
     * 
     * @param Mage_Catalog_Model_Category|Mage_Catalog_Model_Product $item
     * @return void
     */
    public function removeItem($item)
    {
        if($item instanceof Mage_Catalog_Model_Category)
        {
            //The item is a category, the sku will be category-<id>
            $sku = 'category-' . $item->getId();

            //Prepare the error for later use.
            $error = $this->__('Something went wrong while removing the category SEO status.');

            //Obtain the score object for later use.
            $score = Mage::getModel('reload_seo/score')->loadById($item->getId(), 'category');
            $type = 'category';
        }
        elseif($item instanceof Mage_Catalog_Model_Product)
        {
            //The item is a product, the sku will be used as is.
            $sku = $item->getSku();

            //Prepare the error for later use.
            $error = $this->__('Something went wrong while removing the product SEO status.');

            //Obtain the score object for later use.
            $score = Mage::getModel('reload_seo/score')->loadById($item->getId(), 'product');
            $type = 'product';
        }
        else
        {
            Mage::throwException('The requested items is not a product nor a category.');
        }

        //Create the url for the update.
        $url = $this->buildUrl('',
            array(
                'key' => Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key'), 
                'language' => Mage::app()->getLocale()->getLocaleCode(),
                'type' => $type,
                'framework' => 'magento',
                'product[sku]' => $sku,
                'website' => Mage::getBaseUrl(),
            )
        );

        //Execute the request.
        $result = $this->executeCurlRequest($url, null, false, true);
        if($result === null || !array_key_exists('sku', $result))
        {
            //Something went wrong, throw the prepared error.
            throw new Exception($error);
        }

        try
        {
            $score->delete();
        }
        catch(Exception $ex)
        {
            //Something went wrong, throw the prepared error.
            throw new Exception($error);
        }
    }

    /**
     * getDataForUpdate prepares the data for a score update of a single item.
     * 
     * @param  Mage_Catalog_Model_Category|Mage_Catalog_Model_Product   $item
     * @param  string                                                   $requestKey
     * 
     * @return array
     */
    public function getDataForUpdate($item, $requestKey)
    {
        //Create an array for the post data.
        $data = array();
        if($item instanceof Mage_Catalog_Model_Product)
        {
            //The item is a product, the sku will be used as is.
            $data['product[sku]'] = $item->getSku();
            $data['product[status]'] = $item->getStatus();
            $data['product[visibility'] = $item->getVisibility();

            //Obtain the score object for later use.
            $score = Mage::getModel('reload_seo/score')->loadById($item->getId(), 'product');
            $type = 'product';

            if(Mage::getStoreConfig('reload/reload_seo_group/reload_seo_analyze_images'))
            {
                //Append the image data.
                $data['product[images]'] = array();
                foreach($item->getMediaGalleryImages() as $image)
                {
                    $data['product[images]'][] = array(
                        'url' => $image->getUrl(),
                        'name' => $image->getLabel(),
                    );
                }
            }
        }
        else
        {
            //The item is a category, the sku will be category-<id>
            $data['product[sku]'] = 'category-' . $item->getId();

            //Obtain the score object for later use.
            $score = Mage::getModel('reload_seo/score')->loadById($item->getId(), 'category');
            $type = 'category';
        }

        if($score->getKeywords() == null && Mage::getStoreConfig('reload/reload_seo_group/reload_seo_title_default'))
        {
            $score->generateKeywords($item->getName());
        }

        $data['product[product_id]'] = $type . '-' . $item->getId();
        $data['product[keywords]'] = $score->getKeywords();
        $data['product[store_id]'] = $item->getStoreId();

        //Obtain the field mapping by the type and loop over each field, obtain the data and store it.
        $fieldMapping = $this->getFieldMappings($type);
        foreach($fieldMapping as $external => $internal)
        {
            $data['product[' . $external . ']'] = $item->getData($internal);
        }

        //Create the url for the update.
        $url = $this->buildUrl('show',
            array(
                'key' => Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key'), 
                'language' => Mage::app()->getLocale()->getLocaleCode(),
                'type' => $type,
                'framework' => 'magento',
                'website' => Mage::getBaseUrl(),
            )
        );

        //Check if the sku is not null
        if($data['product[sku]'] === null)
        {
            $data['product[sku]'] = '0';
        }

        //Add the store data.
        $data['stores'] = $this->collectStores();

        return array(
            'data' => $data,
            'url' => $url,
            'save_url' => Mage::helper('adminhtml')->getUrl('adminhtml/seo/saveResult', array('request_key' => $requestKey)),
            'form_key' => Mage::getSingleton('core/session')->getFormKey(),
            'request_key' => $requestKey
        );
    }

    /**
     * getFieldMappings creates the field mapping.
     * 
     * @param  string $type
     * @return array
     */
    public function getFieldMappings($type)
    {
        $fieldMapping = array();
        if($type === 'product')
        {
            //We want the field mapping for a product, loop over all fields.
            foreach($this->fields as $field)
            {
                //Get the attribute code from the configuration, only add it when it was configured.
                $attributeCode = $this->getFieldAttributeCode($field);
                if($attributeCode !== null)
                {
                    $fieldMapping[$field] = $attributeCode;
                }
            }
        }
        else
        {
            //We want the field mapping for a category, the fields are fixed/hardcoded.
            $fieldMapping['name'] = 'name';
            $fieldMapping['description'] = 'description';
            $fieldMapping['url_key'] = 'url_key';
            $fieldMapping['meta_title'] = 'meta_title';
            $fieldMapping['meta_keyword'] = 'meta_keywords';
            $fieldMapping['meta_description'] = 'meta_description';
        }
        return $fieldMapping;
    }

    /**
     * getFieldAttributeCode loads the configured attribute code from the configuration.
     * 
     * @param  string $field
     * @return string
     */
    protected function getFieldAttributeCode($field)
    {
        $attributeCode = Mage::getStoreConfig('reload/reload_seo_mappings/reload_seo_mapping_' . $field);
        if($attributeCode == null)
        {
            return null;
        }
        return $attributeCode;
    }
}
