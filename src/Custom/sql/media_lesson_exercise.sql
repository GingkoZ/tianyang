/*2015-3-27*/
/*创建一个视频播放弹出题目的表结构*/
CREATE TABLE IF NOT EXISTS `media_lesson_exercise` (
  `id` int(10) unsigned NOT NULL COMMENT '系统id',
  `lessonId` int(10) unsigned NOT NULL COMMENT '课时id',
  `questionId` int(10) unsigned NOT NULL COMMENT '问题id',
  `showtime` int(10) unsigned DEFAULT NULL COMMENT '题目出现时间',
  `createdTime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间'
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COMMENT='课中练习表';


ALTER TABLE `media_lesson_exercise`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `media_lesson_exercise`
MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;