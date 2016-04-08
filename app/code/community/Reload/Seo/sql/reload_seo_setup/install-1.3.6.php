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

	CREATE TABLE IF NOT EXISTS `{$installer->getTable('reload_seo/score')}` (
	  `id` bigint(30) unsigned NOT NULL AUTO_INCREMENT,
	  `type` varchar(50) NOT NULL,
	  `reference_id` bigint(30) unsigned NOT NULL,
	  `score` varchar(50) NOT NULL,
	  `color` varchar(15) NOT NULL,
	  PRIMARY KEY (`id`),
	  UNIQUE KEY `type` (`type`,`reference_id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

	CREATE TABLE IF NOT EXISTS `{$installer->getTable('reload_seo/scores_rule')}` (
	  `id` bigint(30) unsigned NOT NULL AUTO_INCREMENT,
	  `score_id` bigint(30) unsigned NOT NULL,
	  `field` varchar(150) NOT NULL,
	  `title` text NOT NULL,
	  `status` varchar(25) NOT NULL,
	  PRIMARY KEY (`id`),
	  KEY `score_id` (`score_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

	ALTER TABLE  `{$installer->getTable('reload_seo/score')}` ADD  `keywords` VARCHAR( 255 ) NOT NULL ;

	ALTER TABLE `{$installer->getTable('reload_seo/scores_rule')}`
	  ADD CONSTRAINT `reload_seo_scores_rule_ibfk_1` FOREIGN KEY (`score_id`) REFERENCES `{$installer->getTable('reload_seo/score')}` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

  	ALTER TABLE  `{$installer->getTable('reload_seo/score')}` ADD  `store_id` INT( 11 ) NOT NULL AFTER  `reference_id`;

	ALTER TABLE `{$installer->getTable('reload_seo/score')}` DROP INDEX `type`;

	ALTER TABLE  `{$installer->getTable('reload_seo/score')}` ADD UNIQUE `type` (`type` ,`reference_id` ,`store_id`);

	ALTER TABLE  `{$installer->getTable('reload_seo/score')}` ADD  `synonyms` VARCHAR( 255 ) NOT NULL ;
");
$installer->endSetup();