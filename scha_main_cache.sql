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
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel_cache_harlequinscourt@gmail.com|41.90.141.20','i:1;',1782395150),('laravel_cache_harlequinscourt@gmail.com|41.90.141.20:timer','i:1782395150;',1782395150),('laravel_cache_kibettdennis@gmail.com|41.90.141.20','i:1;',1782412211),('laravel_cache_kibettdennis@gmail.com|41.90.141.20:timer','i:1782412211;',1782412211),('laravel_cache_kingserenity@gmail.com|41.90.141.20','i:1;',1782395157),('laravel_cache_kingserenity@gmail.com|41.90.141.20:timer','i:1782395157;',1782395157),('laravel_cache_system_settings','O:32:\"App\\Modules\\System\\Models\\System\":33:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:6:\"system\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:29:{s:2:\"id\";i:1;s:4:\"name\";s:7:\"Scharge\";s:4:\"logo\";s:8:\"logo.svg\";s:9:\"logo_dark\";s:9:\"logo.svg\n\";s:9:\"logo_icon\";s:9:\"logo.svg\n\";s:7:\"favicon\";N;s:6:\"slogan\";s:41:\"Simplifying Property & Utility Management\";s:8:\"timezone\";s:3:\"UTC\";s:11:\"date_format\";s:5:\"d-m-Y\";s:11:\"time_format\";s:5:\"H:i:s\";s:8:\"currency\";s:3:\"AUD\";s:15:\"currency_symbol\";s:3:\"KSh\";s:13:\"primary_color\";s:7:\"#b30000\";s:15:\"secondary_color\";s:7:\"#008cff\";s:13:\"contact_email\";N;s:13:\"contact_phone\";N;s:7:\"address\";N;s:8:\"location\";s:85:\"\"{\\\"country\\\":null,\\\"city\\\":null,\\\"name\\\":null,\\\"latitude\\\":null,\\\"longitude\\\":null}\"\";s:16:\"meta_description\";N;s:13:\"meta_keywords\";N;s:16:\"maintenance_mode\";i:0;s:16:\"pagination_limit\";i:15;s:10:\"custom_css\";N;s:9:\"custom_js\";N;s:8:\"settings\";s:614:\"{\"notifications\":{\"email_notifications\":true,\"push_notifications\":true,\"sms_notifications\":false,\"notification_sound\":true},\"security\":{\"two_factor_auth\":false,\"login_attempts\":5,\"session_timeout\":30,\"password_expiry\":90},\"integrations\":{\"google_analytics\":\"\",\"google_maps_key\":\"\",\"mail_driver\":\"smtp\",\"mail_host\":\"\",\"mail_port\":\"587\",\"mail_username\":\"\",\"mail_password\":\"\"},\"backup\":{\"auto_backup\":true,\"backup_frequency\":\"daily\",\"backup_retention\":30,\"backup_to_cloud\":false},\"company\":{\"website\":\"\",\"phone\":\"\",\"email\":\"\",\"address\":\"\",\"about\":\"\",\"mission\":\"\",\"vision\":\"\",\"values\":\"\"},\"currency_position\":\"before\"}\";s:13:\"website_pages\";s:359:\"{\"home\":{\"enabled\":true,\"title\":\"Home\",\"slug\":\"\",\"show_in_menu\":true,\"order\":1},\"about\":{\"enabled\":true,\"title\":\"About Us\",\"slug\":\"about\",\"show_in_menu\":true,\"order\":2},\"services\":{\"enabled\":true,\"title\":\"Services\",\"slug\":\"services\",\"show_in_menu\":true,\"order\":3},\"contact\":{\"enabled\":true,\"title\":\"Contact Us\",\"slug\":\"contact\",\"show_in_menu\":true,\"order\":4}}\";s:12:\"social_media\";s:441:\"{\"facebook\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-facebook-fill\",\"name\":\"Facebook\",\"color\":\"#1877F2\",\"order\":1},\"twitter\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-twitter-fill\",\"name\":\"Twitter\",\"color\":\"#1DA1F2\",\"order\":2},\"instagram\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-instagram-fill\",\"name\":\"Instagram\",\"color\":\"#E4405F\",\"order\":3},\"linkedin\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-linkedin-fill\",\"name\":\"LinkedIn\",\"color\":\"#0A66C2\",\"order\":4}}\";s:10:\"created_at\";s:19:\"2026-04-26 17:13:04\";s:10:\"updated_at\";s:19:\"2026-06-24 23:58:08\";}s:11:\"\0*\0original\";a:29:{s:2:\"id\";i:1;s:4:\"name\";s:7:\"Scharge\";s:4:\"logo\";s:8:\"logo.svg\";s:9:\"logo_dark\";s:9:\"logo.svg\n\";s:9:\"logo_icon\";s:9:\"logo.svg\n\";s:7:\"favicon\";N;s:6:\"slogan\";s:41:\"Simplifying Property & Utility Management\";s:8:\"timezone\";s:3:\"UTC\";s:11:\"date_format\";s:5:\"d-m-Y\";s:11:\"time_format\";s:5:\"H:i:s\";s:8:\"currency\";s:3:\"AUD\";s:15:\"currency_symbol\";s:3:\"KSh\";s:13:\"primary_color\";s:7:\"#b30000\";s:15:\"secondary_color\";s:7:\"#008cff\";s:13:\"contact_email\";N;s:13:\"contact_phone\";N;s:7:\"address\";N;s:8:\"location\";s:85:\"\"{\\\"country\\\":null,\\\"city\\\":null,\\\"name\\\":null,\\\"latitude\\\":null,\\\"longitude\\\":null}\"\";s:16:\"meta_description\";N;s:13:\"meta_keywords\";N;s:16:\"maintenance_mode\";i:0;s:16:\"pagination_limit\";i:15;s:10:\"custom_css\";N;s:9:\"custom_js\";N;s:8:\"settings\";s:614:\"{\"notifications\":{\"email_notifications\":true,\"push_notifications\":true,\"sms_notifications\":false,\"notification_sound\":true},\"security\":{\"two_factor_auth\":false,\"login_attempts\":5,\"session_timeout\":30,\"password_expiry\":90},\"integrations\":{\"google_analytics\":\"\",\"google_maps_key\":\"\",\"mail_driver\":\"smtp\",\"mail_host\":\"\",\"mail_port\":\"587\",\"mail_username\":\"\",\"mail_password\":\"\"},\"backup\":{\"auto_backup\":true,\"backup_frequency\":\"daily\",\"backup_retention\":30,\"backup_to_cloud\":false},\"company\":{\"website\":\"\",\"phone\":\"\",\"email\":\"\",\"address\":\"\",\"about\":\"\",\"mission\":\"\",\"vision\":\"\",\"values\":\"\"},\"currency_position\":\"before\"}\";s:13:\"website_pages\";s:359:\"{\"home\":{\"enabled\":true,\"title\":\"Home\",\"slug\":\"\",\"show_in_menu\":true,\"order\":1},\"about\":{\"enabled\":true,\"title\":\"About Us\",\"slug\":\"about\",\"show_in_menu\":true,\"order\":2},\"services\":{\"enabled\":true,\"title\":\"Services\",\"slug\":\"services\",\"show_in_menu\":true,\"order\":3},\"contact\":{\"enabled\":true,\"title\":\"Contact Us\",\"slug\":\"contact\",\"show_in_menu\":true,\"order\":4}}\";s:12:\"social_media\";s:441:\"{\"facebook\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-facebook-fill\",\"name\":\"Facebook\",\"color\":\"#1877F2\",\"order\":1},\"twitter\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-twitter-fill\",\"name\":\"Twitter\",\"color\":\"#1DA1F2\",\"order\":2},\"instagram\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-instagram-fill\",\"name\":\"Instagram\",\"color\":\"#E4405F\",\"order\":3},\"linkedin\":{\"enabled\":false,\"url\":\"\",\"icon\":\"ri-linkedin-fill\",\"name\":\"LinkedIn\",\"color\":\"#0A66C2\",\"order\":4}}\";s:10:\"created_at\";s:19:\"2026-04-26 17:13:04\";s:10:\"updated_at\";s:19:\"2026-06-24 23:58:08\";}s:10:\"\0*\0changes\";a:0:{}s:11:\"\0*\0previous\";a:0:{}s:8:\"\0*\0casts\";a:5:{s:16:\"maintenance_mode\";s:7:\"boolean\";s:8:\"settings\";s:5:\"array\";s:13:\"website_pages\";s:5:\"array\";s:12:\"social_media\";s:5:\"array\";s:8:\"location\";s:5:\"array\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:27:\"\0*\0relationAutoloadCallback\";N;s:26:\"\0*\0relationAutoloadContext\";N;s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:26:{i:0;s:4:\"name\";i:1;s:4:\"logo\";i:2;s:9:\"logo_dark\";i:3;s:9:\"logo_icon\";i:4;s:7:\"favicon\";i:5;s:6:\"slogan\";i:6;s:8:\"timezone\";i:7;s:11:\"date_format\";i:8;s:11:\"time_format\";i:9;s:8:\"currency\";i:10;s:15:\"currency_symbol\";i:11;s:13:\"primary_color\";i:12;s:15:\"secondary_color\";i:13;s:13:\"contact_email\";i:14;s:13:\"contact_phone\";i:15;s:7:\"address\";i:16;s:8:\"location\";i:17;s:16:\"meta_description\";i:18;s:13:\"meta_keywords\";i:19;s:16:\"maintenance_mode\";i:20;s:16:\"pagination_limit\";i:21;s:10:\"custom_css\";i:22;s:9:\"custom_js\";i:23;s:8:\"settings\";i:24;s:13:\"website_pages\";i:25;s:12:\"social_media\";}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}',2097749616);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
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
