<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Reload_Seo_Helper_Abstract contains the basic functionality for the Reload_Seo_Helper_Data class
 */
class Reload_Seo_Helper_Abstract extends Mage_Core_Helper_Abstract
{
    /**
     * Variable for storing the API url in.
     * @var string
     */
    protected $url = 'https://api.reloadseo.com/api/';

    /**
     * Variable for storing the API version in.
     * @var string
     */
    protected $version = 'v1/';

    /**
     * buildUrl creates the url with the given action and parameters.
     * 
     * @param  string $action
     * @param  array  $params
     * @return string      
     */
    public function buildUrl($action, $params = array())
    {
        $url = $this->url . $this->version . 'seo';

        if($action != null)
        {
            $url .= '/' . $action;
        }

        if($params != null)
        {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * exec executes an API call to the Reload API.
     * 
     * @param  string  $url
     * @param  array|string  $postdata
     * @param  boolean $postAsString If true, the $postdata is an string.
     * @return array
     */
    protected function executeCurlRequest($url, $postdata = null, $postAsString = false, $isDelete = false)
    {
        //Obtain an curl handle.
        $ch = curl_init($url);

        //Tell the handle to wait for a response.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if($postdata !== null)
        {
            //Set the handle to make an POST request.
            curl_setopt($ch, CURLOPT_POST, 1);
            if($postAsString)
            {
                //Set the post data
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
            }
            else
            {
                //Build the post data query and set it.
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
            }
            //Set the content type.
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        }
        elseif($isDelete)
        {
            //Set the handle to make an DELETE request.
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        //Execute the request and close the handle.
        $result = curl_exec($ch);
        curl_close($ch);

        //Decode the result and return the value if successfull.
        $result = json_decode($result, true);
        if($result)
        {
            return $result;
        }
        return null;
    }

    /**
     * collectStores collects the data about the websites and stores.
     * 
     * @return array
     */
    public function collectStores()
    {
        $storesData = array();

        //Loop over the websites.
        foreach(Mage::app()->getWebsites() as $website)
        {
            $websiteData = array(
                'id' => $website->getWebsiteId(),
                'name' => $website->getName(),
                'code' => $website->getCode(),
                'stores' => array()
            );

            //Loop over the stores.
            foreach($website->getStores() as $store)
            {
                //Check wheter the secure url or non-secure url is used in the frontend.
                if(Mage::getStoreConfig('web/secure/use_in_frontend', $store->getStoreId()))
                {
                    $frontendBaseUrl = Mage::app()->getStore($store->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, array('_secure' => true));
                }
                else
                {
                    $frontendBaseUrl = Mage::app()->getStore($store->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
                }

                $websiteData['stores'][] = array(
                    'id' => $store->getStoreId(),
                    'name' => $store->getName(),
                    'code' => $store->getCode(),
                    'is_active' => $store->getIsActive(),
                    'frontend_base_url' => $frontendBaseUrl
                );
            }

            $storesData[] = $websiteData;
        }
        return $storesData;
    }
}