--
-- 表的结构 `tianyangtax_organization`
--

CREATE TABLE IF NOT EXISTS `tianyangtax_organization` (
  `id` int(10) unsigned NOT NULL,
  `code` varchar(64) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL,
  `weight` int(11) NOT NULL DEFAULT '0',
  `parentId` int(10) unsigned NOT NULL DEFAULT '0',
  `description` text
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;



ALTER TABLE `tianyangtax_organization`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `tianyangtax_organization`
MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;


INSERT INTO `edusoho-tianyangtax`.`tianyangtax_organization` (`id`, `code`, `name`, `weight`, `parentId`, `description`) VALUES ('1', '1.', '测试组织', '0', '0', '测试组织');
