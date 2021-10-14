CREATE TABLE IF NOT EXISTS `__PREFIX__wok_crontab_app` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '应用App_id',
  `enable` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '启用',
  `create_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '更新时间',
  `name` varchar(55) NOT NULL DEFAULT '' COMMENT '名称',
  `secret` varchar(55) NOT NULL DEFAULT '' COMMENT '应用Secret_key',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10001 DEFAULT CHARSET=utf8 COMMENT='定时应用';

CREATE TABLE `__PREFIX__wok_crontab_task` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `app_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '应用App_id',
  `name` varchar(55) NOT NULL DEFAULT '' COMMENT '名称',
  `rule` varchar(55) NOT NULL DEFAULT '' COMMENT '规则',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT 'url',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `tag` varchar(55) NOT NULL DEFAULT '' COMMENT '标签',
  `last_run_info` varchar(55) NOT NULL DEFAULT '' COMMENT '最后一次运行信息',
  `last_run_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '最后一次运行时间',
  `create_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '1970-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_app_id` (`app_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10001 DEFAULT CHARSET=utf8 COMMENT='定时任务';
