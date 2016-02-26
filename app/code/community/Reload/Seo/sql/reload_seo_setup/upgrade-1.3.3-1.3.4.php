<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

$installer = $this;
$installer->startSetup();
$installer->run("
	ALTER TABLE  `{$installer->getTable('reload_seo/score')}` ADD  `synonyms` VARCHAR( 255 ) NOT NULL ;
");
$installer->endSetup();