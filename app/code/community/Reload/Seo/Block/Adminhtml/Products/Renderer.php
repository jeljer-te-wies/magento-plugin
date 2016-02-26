<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Reload_Seo_Block_Adminhtml_Products_Renderer is used to render the product grid it's SEO score column
 */
class Reload_Seo_Block_Adminhtml_Products_Renderer extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
	/**
	 * render is called when a row is drawn in the grid.
	 * 
	 * @param  Varien_Object $row
	 * @return string
	 */
	public function render(Varien_Object $row)
	{
		if($this->getColumn()->getIndex() === 'seo_score')
		{
			$updateKey = $row->getId() . '-product-' . (int) Mage::app()->getRequest()->getParam('store');

			//Only render the seo score column.
			if($row->getSeoScore() === null)
			{
				//No SEO score is known yet.
				return '<div class="seo-score-grid ' .$updateKey . '">' . Mage::helper('reload_seo')->__('Unknown') . '</div>';
			}

			//Create the score html and return it.
			$score = $row->getSeoScore();
			return '<div class="seo-score-grid ' .$updateKey . '"><div style="background-color: ' . $row->getSeoColor() . '; width: 18px; height: 18px; float: left; border-radius: 100px;"></div>' . $score . '</div>';
		}		 
	}
}