-- Visa and holiday posts (2026-11-09): the flyers staff post in Admin → Visa & holiday posts.
-- Run once on an existing database (after visa-upgrade.sql). A fresh install gets the table
-- from schema.sql and these first posts from demo-content.sql.

CREATE TABLE IF NOT EXISTS `visa_offers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kind` enum('visa','holiday') NOT NULL DEFAULT 'visa',
  `title` varchar(150) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `image` varchar(100) NOT NULL,
  `destination_id` int(10) unsigned DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(6) NOT NULL DEFAULT 100,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_vo_list` (`kind`,`is_active`,`sort_order`),
  KEY `fk_vo_dest` (`destination_id`),
  KEY `fk_vo_user` (`created_by`),
  CONSTRAINT `fk_vo_dest` FOREIGN KEY (`destination_id`) REFERENCES `visa_destinations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vo_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- First posts: Fanel's flyers (images in assets/img/fanel/). Only added when the table is empty.
INSERT INTO visa_offers (kind, title, image, destination_id, sort_order)
SELECT * FROM (
  SELECT 'visa' AS kind, 'Dubai 5-year multiple-entry visa' AS title, 'fanel/dubai-multiple-entry' AS image, (SELECT id FROM visa_destinations WHERE slug = 'united-arab-emirates') AS destination_id, 10 AS sort_order UNION ALL
  SELECT 'visa', 'Thailand visa', 'fanel/thailand-visa', (SELECT id FROM visa_destinations WHERE slug = 'thailand'), 20 UNION ALL
  SELECT 'visa', 'Malaysia visa', 'fanel/malaysia-visa', (SELECT id FROM visa_destinations WHERE slug = 'malaysia'), 30 UNION ALL
  SELECT 'visa', 'Qatar visa', 'fanel/qatar-visa', NULL, 40 UNION ALL
  SELECT 'visa', 'Kenya visa', 'fanel/kenya-visa', NULL, 50 UNION ALL
  SELECT 'visa', 'Indonesia visa', 'fanel/indonesia-visa', NULL, 60 UNION ALL
  SELECT 'visa', 'Mexico tourist visa', 'fanel/mexico-visa', NULL, 70 UNION ALL
  SELECT 'visa', 'Uganda visa', 'fanel/uganda-visa', NULL, 80 UNION ALL
  SELECT 'visa', 'East Africa tourist visa', 'fanel/east-africa-visa', NULL, 90 UNION ALL
  SELECT 'visa', 'Kuwait and Oman work visas', 'fanel/kuwait-oman-work-visa', NULL, 100 UNION ALL
  SELECT 'visa', 'Qatar work visa', 'fanel/qatar-work-visa', NULL, 110 UNION ALL
  SELECT 'visa', 'Indonesia 2-year residence permit', 'fanel/indonesia-residence', NULL, 120 UNION ALL
  SELECT 'visa', 'Dubai e-visa (non-Nigerians)', 'fanel/dubai-evisa', (SELECT id FROM visa_destinations WHERE slug = 'united-arab-emirates'), 130 UNION ALL
  SELECT 'visa', 'More countries we process', 'fanel/visa-countries', NULL, 140 UNION ALL
  SELECT 'holiday', 'Seychelles holiday', 'fanel/seychelles', NULL, 10 UNION ALL
  SELECT 'holiday', 'Family escape to Qatar', 'fanel/qatar-family', NULL, 20 UNION ALL
  SELECT 'holiday', 'Seychelles and Ethiopia', 'fanel/seychelles-ethiopia', NULL, 30
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM visa_offers);
