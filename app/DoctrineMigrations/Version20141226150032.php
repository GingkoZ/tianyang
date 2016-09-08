<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20141226150032 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql("	
	        CREATE TABLE IF NOT EXISTS `task` (
			  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `type` enum('single','cycle') NOT NULL DEFAULT 'single',
			  `startTime` int(10) unsigned NOT NULL DEFAULT '0',
			  `taskName` varchar(255) NOT NULL DEFAULT '',
			  `status` enum('open','close') NOT NULL DEFAULT 'open',
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
    	");     

    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
