-- MySQL dump 10.13  Distrib 8.0.41, for macos15 (x86_64)
--
-- Host: localhost    Database: scha_main
-- ------------------------------------------------------
-- Server version	9.4.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `system`
--

DROP TABLE IF EXISTS `system`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_dark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `favicon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slogan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `date_format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'd-m-Y',
  `time_format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'H:i:s',
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'KES',
  `currency_symbol` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'KSh',
  `primary_color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secondary_color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `location` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `maintenance_mode` tinyint(1) NOT NULL DEFAULT '0',
  `pagination_limit` int NOT NULL DEFAULT '15',
  `custom_css` text COLLATE utf8mb4_unicode_ci,
  `custom_js` text COLLATE utf8mb4_unicode_ci,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `website_pages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `social_media` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system`
--

LOCK TABLES `system` WRITE;
/*!40000 ALTER TABLE `system` DISABLE KEYS */;
INSERT INTO `system` VALUES (1,'Scharge','logo.svg','logo.svg\n','logo.svg\n',NULL,'Simplifying Property & Utility Management','UTC','d-m-Y','H:i:s','AUD','KSh','#b30000','#008cff',NULL,NULL,NULL,'\"{\\\"country\\\":null,\\\"city\\\":null,\\\"name\\\":null,\\\"latitude\\\":null,\\\"longitude\\\":null}\"',NULL,NULL,0,15,NULL,NULL,'{\"notifications\":{\"email_notifications\":true,\"push_notifications\":true,\"sms_notifications\":false,\"notification_sound\":true},\"security\":{\"two_factor_auth\":false,\"login_attempts\":5,\"session_timeout\":30,\"password_expiry\":90},\"integrations\":{\"google_analytics\":\"\",\"google_maps_key\":\"\",\"mail_driver\":\"smtp\",\"mail_host\":\"\",\"mail_port\":\"587\",\"mail_username\":\"\",\"mail_password\":\"\"},\"backup\":{\"auto_backup\":true,\"backup_frequency\":\"daily\",\"backup_retention\":30,\"backup_to_cloud\":false},\"company\":{\"website\":\"\",\"phone\":\"\",\"email\":\"\",\"address\":\"\",\"about\":\"\",\"mission\":\"\",\"vision\":\"\",\"values\":\"\"},\"currency_position\":\"before\"}','{\"home\":{\"enabled\":true,\"title\":\"Home\",\"slug\":\"\",\"show_in_menu\":true,\"order\":1},\"about\":{\"enabled\":true,\"title\":\"About Us\",\"slug\":\"about\",\"show_in_menu\":true,\"order\":2},\"services\":{\"enabled\":true,\"title\":\"Services\",\"slug\":\"services\",\"show_in_menu\":true,\"order\":3},\"contact\":{\"enabled\":true,\"title\":\"Contact Us\",\"slug\":\"contact\",\"show_in_menu\":true,\"order\":4}}','{\"facebook\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-facebook-fill\",\"name\":\"Facebook\",\"color\":\"#1877F2\",\"order\":1},\"twitter\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-twitter-fill\",\"name\":\"Twitter\",\"color\":\"#1DA1F2\",\"order\":2},\"instagram\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-instagram-fill\",\"name\":\"Instagram\",\"color\":\"#E4405F\",\"order\":3},\"linkedin\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-linkedin-fill\",\"name\":\"LinkedIn\",\"color\":\"#0A66C2\",\"order\":4}}','2026-04-26 15:13:04','2026-06-24 21:58:08');
/*!40000 ALTER TABLE `system` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-29 16:15:01
