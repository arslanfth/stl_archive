-- 3D Release Schema
-- Bu dosya sıfırdan kurulum içindir.
-- Kişisel Google bilgisi içermez.
-- Varsayılan kategorileri ekler.
-- Model verisi eklemez.

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_categories_sort_order` (`sort_order`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int DEFAULT NULL,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `size` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `download_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `drive_file_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `drive_file_id` (`drive_file_id`),
  KEY `category_id` (`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `image_media` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int NOT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_cover` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_image_media_image_id` (`image_id`),
  KEY `idx_image_media_image_cover` (`image_id`,`is_cover`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `google_drive_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `client_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `redirect_uri` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `folder_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `refresh_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `token_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `expires_at` datetime DEFAULT NULL,
  `raw_token_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_connected` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan kategori seed verileri
INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT '3D Yazıcı Parça', '#7c5cff', 10
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = '3D Yazıcı Parça');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Araç Gereç', '#39a0ff', 20
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Araç Gereç');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Dekorasyon', '#31c48d', 30
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Dekorasyon');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Evcil Hayvan', '#f59e0b', 40
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Evcil Hayvan');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Figür', '#ec4899', 50
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Figür');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Hediyelik Eşya', '#94a3b8', 60
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Hediyelik Eşya');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Hobi Araçları', '#ef4444', 70
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Hobi Araçları');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Konsol Aksesuar', '#14b8a6', 80
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Konsol Aksesuar');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Motorlu Taşıtlar', '#eab308', 90
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Motorlu Taşıtlar');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Oto Parça', '#8b5cf6', 100
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Oto Parça');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Oyuncak&Oyun', '#06b6d4', 110
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Oyuncak&Oyun');

INSERT INTO `categories` (`name`, `color`, `sort_order`)
SELECT 'Reçine Baskı', '#f97316', 120
WHERE NOT EXISTS (SELECT 1 FROM `categories` WHERE `name` = 'Reçine Baskı');

-- Google Drive ayarları için boş başlangıç satırı
INSERT INTO `google_drive_settings` (
  `id`,
  `client_id`,
  `client_secret`,
  `redirect_uri`,
  `folder_id`,
  `access_token`,
  `refresh_token`,
  `token_type`,
  `scope`,
  `expires_at`,
  `raw_token_json`,
  `is_connected`
)
SELECT
  1,
  NULL,
  NULL,
  NULL,
  NULL,
  NULL,
  NULL,
  NULL,
  NULL,
  NULL,
  NULL,
  0
WHERE NOT EXISTS (
  SELECT 1 FROM `google_drive_settings` WHERE `id` = 1
);
