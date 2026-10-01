CREATE TABLE `localization_text` (
  `id` int(1) UNSIGNED NOT null AUTO_INCREMENT,
  `text` varchar(255) NOT null,
  `ts` timestamp NOT null DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE (`text`)
) ENGINE=InnoDB CHARSET=utf8 COLLATE=utf8_bin COMMENT='default texts for translations';

CREATE TABLE `localization` (
  `id` int(1) UNSIGNED NOT null AUTO_INCREMENT,
  `text_id` int(1) UNSIGNED NOT null,
  `lang` char(2) NOT null,
  `variant` tinyint(1) UNSIGNED NOT null DEFAULT 0,
  `translation` varchar(255) NOT null,
  `ts` timestamp NOT null DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE (`text_id`, `lang`, `variant`),
  CONSTRAINT `x` FOREIGN KEY (`text_id`) REFERENCES `localization_text` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='text translations';
