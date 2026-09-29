CREATE TABLE `localization_untranslated` (
  `id` int(1) UNSIGNED NOT null AUTO_INCREMENT,
  `lang` varchar(12) NOT null,
  `ns` varchar(24) NOT null DEFAULT '' COMMENT 'empty = no namespace',
  `hash` char(40) CHARACTER SET ascii NOT null COMMENT 'SHA-1 of text (unique key for texts of any length)',
  `text` text NOT null,
  `ts` timestamp NOT null DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE (`lang`, `ns`, `hash`)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='texts requested without translation';
