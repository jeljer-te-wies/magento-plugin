<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Reload_Seo_Block_Adminhtml_Seo shows the actual seo score and rules for the current product or category.
 */
class Reload_Seo_Block_Adminhtml_Seo extends Mage_Adminhtml_Block_Template
{
	/**
	 * Variable to keep track if the score has been loaded from the database.
	 * @var boolean
	 */
	protected $_scoreLoaded = false;

	/**
	 * Variable to keep track of the loaded score model.
	 * @var Reload_Seo_Model_Score
	 */
	protected $_score;

	/**
	 * Basic constructor.
	 */
	public function __construct()
	{
		//Set the template for when this block is used through the observers or through ajax.
		$this->setTemplate('reload_seo/seo.phtml');
	}

	/**
	 * isProductView returns a boolean which indicates whether this is a product view or an category view.
	 * 
	 * @return boolean
	 */
	public function isProductView()
	{
		return (bool)$this->getIsProductView();
	}

	/**
	 * getScore loads the score model from the database linked to the current product or category.
	 * 
	 * @return Reload_Seo_Model_Score|null
	 */
	public function getScore()
	{
		//Check if the score has already been loaded.
		if(!$this->_scoreLoaded)
		{
			if($this->isProductView())
			{
				//This is an product view, obtain the product id.
				$referenceId = Mage::registry('current_product')->getId();
				$type = 'product';
			}
			else
			{
				//This is an category view, obtain the category id.
				$referenceId = Mage::registry('category')->getId();
				$type = 'category';
			}

			if(Mage::registry('seo_score') != null && Mage::registry('seo_score')->getId() == $referenceId)
			{
				$this->_score = Mage::registry('seo_score');
			}
			else
			{
				//If the reference === null, we want to load the 0 object.
				if($referenceId === null)
				{
					$referenceId = 0;
				}

				//Load the score from the database where the reference_id and type matches.
				$this->_score = Mage::getModel('reload_seo/score')->loadById($referenceId, $type);

				if($this->_score == null || $this->_score->getReferenceId() != $referenceId)
				{
					//No score object was found.
					$this->_score = null;
				}
			}
			
			$this->_scoreLoaded = true;
		}
		return $this->_score;
	}

	public function getBaseShopUrl()
	{
		if($this->getIsProductView())
		{
			$storeId = Mage::registry('current_product')->getStoreId();
			$productId = Mage::registry('current_product')->getId();

			$appEmulation = Mage::getSingleton('core/app_emulation');
			$initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
 
			$productUrl = Mage::getBaseUrl(); //Mage::getModel('catalog/product')->load($productId)->getProductUrl();

			$appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);

			return $productUrl;
		}
		else
		{
			$storeId = Mage::registry('category')->getStoreId();
			$catId = Mage::registry('category')->getId();

			$appEmulation = Mage::getSingleton('core/app_emulation');
			$initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
 
			$catUrl = Mage::getBaseUrl(); //Mage::getModel('catalog/category')->load($catId)->getUrl();

			$appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);

			return $catUrl;
		}
		return null;
	}

	public function getUpdateRequestKey()
	{
		if($this->isProductView())
		{
			//This is an product view, obtain the product id.
			$referenceId = Mage::registry('current_product')->getId();
			$type = 'product';
		}
		else
		{
			//This is an category view, obtain the category id.
			$referenceId = Mage::registry('category')->getId();
			$type = 'category';
		}

		$storeId = (int) $this->getStoreId();
		return $referenceId . '-' . $type . '-' . $storeId;
	}

	public function getStoreId()
	{
		if($this->isProductView())
		{
			return Mage::registry('current_product')->getStoreId();
		}
		else
		{
			return Mage::registry('category')->getStoreId();
		}
	}
}