-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: lost_and_found_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,2,'USER_REGISTER','New user registered: jacky','root@localhost','2026-05-24 10:52:32'),(2,2,'LOST_REPORT','Lost report submitted for item: ,bvcvhk\';',NULL,'2026-05-24 10:54:01'),(3,2,'FOUND_REPORT','Found report submitted for item: ,bvcvhk\';',NULL,'2026-05-24 10:57:33');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Electronics','Laptops, phones, tablets, chargers, headphones etc.',1),(2,'Documents','IDs, passports, certificates, papers etc.',1),(3,'Accessories','Watches, jewelry, glasses, bags etc.',1),(4,'Clothing','Jackets, shoes, hats, uniforms etc.',1),(5,'Keys','House keys, car keys, keychains etc.',1),(6,'Books','Textbooks, notebooks, magazines etc.',1),(7,'Wallet/Purse','Wallets, purses, money, cards etc.',1),(8,'Sports Equipment','Balls, rackets, gym equipment etc.',1),(9,'Medical','Glasses, hearing aids, medications etc.',1),(10,'Others','Miscellaneous items',1);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `claims`
--

DROP TABLE IF EXISTS `claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lost_report_id` int(11) NOT NULL,
  `found_report_id` int(11) NOT NULL,
  `claimant_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lost_report_id` (`lost_report_id`),
  KEY `found_report_id` (`found_report_id`),
  KEY `claimant_id` (`claimant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `claims_ibfk_1` FOREIGN KEY (`lost_report_id`) REFERENCES `lost_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `claims_ibfk_2` FOREIGN KEY (`found_report_id`) REFERENCES `found_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `claims_ibfk_3` FOREIGN KEY (`claimant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `claims`
--

LOCK TABLES `claims` WRITE;
/*!40000 ALTER TABLE `claims` DISABLE KEYS */;
/*!40000 ALTER TABLE `claims` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deleted_items`
--

DROP TABLE IF EXISTS `deleted_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_id` int(11) NOT NULL,
  `report_type` enum('lost','found') NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `date_reported` date NOT NULL,
  `deleted_by` enum('admin','user') NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_original_id` (`original_id`),
  KEY `idx_report_type` (`report_type`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deleted_items`
--

LOCK TABLES `deleted_items` WRITE;
/*!40000 ALTER TABLE `deleted_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `deleted_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `found_reports`
--

DROP TABLE IF EXISTS `found_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `found_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `date_found` date NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `claimed_by` int(11) DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL,
  `photo` varchar(500) DEFAULT NULL,
  `photo_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_date_found` (`date_found`),
  KEY `idx_found_reports_search` (`item_name`,`category`,`location`,`status`),
  CONSTRAINT `found_reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `found_reports`
--

LOCK TABLES `found_reports` WRITE;
/*!40000 ALTER TABLE `found_reports` DISABLE KEYS */;
INSERT INTO `found_reports` VALUES (1,2,',bvcvhk\';','Others','lHDHASF;LHASHFP\'ASF','library','2026-05-24','approved','2026-05-24 10:57:33',NULL,NULL,'uploads/found_items/6a12d99db0892.jpg',NULL);
/*!40000 ALTER TABLE `found_reports` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER after_found_report_insert
AFTER INSERT ON found_reports
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, details)
    VALUES (NEW.user_id, 'FOUND_REPORT', CONCAT('Found report submitted for item: ', NEW.item_name));
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `lost_reports`
--

DROP TABLE IF EXISTS `lost_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lost_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `date_lost` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('open','resolved','closed') DEFAULT 'open',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category` (`category`),
  KEY `idx_date_lost` (`date_lost`),
  KEY `idx_lost_reports_search` (`item_name`,`category`,`location`),
  CONSTRAINT `lost_reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lost_reports`
--

LOCK TABLES `lost_reports` WRITE;
/*!40000 ALTER TABLE `lost_reports` DISABLE KEYS */;
INSERT INTO `lost_reports` VALUES (1,2,',bvcvhk\';','Others','uaskfplasfaKSJhfaknf','library','2026-05-24','2026-05-24 10:54:01','open');
/*!40000 ALTER TABLE `lost_reports` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER after_lost_report_insert
AFTER INSERT ON lost_reports
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, details)
    VALUES (NEW.user_id, 'LOST_REPORT', CONCAT('Lost report submitted for item: ', NEW.item_name));
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Temporary table structure for view `matched_items`
--

DROP TABLE IF EXISTS `matched_items`;
/*!50001 DROP VIEW IF EXISTS `matched_items`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `matched_items` AS SELECT
 1 AS `lost_id`,
  1 AS `lost_item`,
  1 AS `category`,
  1 AS `lost_location`,
  1 AS `date_lost`,
  1 AS `found_id`,
  1 AS `found_item`,
  1 AS `found_location`,
  1 AS `date_found`,
  1 AS `finder_id`,
  1 AS `owner_id` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `system_stats`
--

DROP TABLE IF EXISTS `system_stats`;
/*!50001 DROP VIEW IF EXISTS `system_stats`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `system_stats` AS SELECT
 1 AS `total_users`,
  1 AS `total_admins`,
  1 AS `total_regular_users`,
  1 AS `total_lost_reports`,
  1 AS `total_found_reports`,
  1 AS `pending_approvals`,
  1 AS `total_claims`,
  1 AS `pending_claims`,
  1 AS `approved_claims`,
  1 AS `deleted_items_count` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_suspended` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_username` (`username`),
  KEY `idx_users_is_suspended` (`is_suspended`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','admin@example.com','$2y$10$PQl1QymdaNCOOjM5tGGJRedtrcWxaLVrDbAqc66LkPv5hBbqOIx/O','admin',NULL,NULL,'2026-05-24 10:13:18',0,NULL,NULL),(2,'jacky','jacklinemachim@gmail.com','$2y$10$elT0RR4Cc5pUZXDVWzWxseY7YtaJGVkOJtUZGfG7kTOjJHBsB.tvC','user',NULL,NULL,'2026-05-24 10:52:32',0,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER after_user_register
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, details, ip_address)
    VALUES (NEW.id, 'USER_REGISTER', CONCAT('New user registered: ', NEW.username), USER());
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `matched_items`
--

/*!50001 DROP VIEW IF EXISTS `matched_items`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `matched_items` AS select `l`.`id` AS `lost_id`,`l`.`item_name` AS `lost_item`,`l`.`category` AS `category`,`l`.`location` AS `lost_location`,`l`.`date_lost` AS `date_lost`,`f`.`id` AS `found_id`,`f`.`item_name` AS `found_item`,`f`.`location` AS `found_location`,`f`.`date_found` AS `date_found`,`f`.`user_id` AS `finder_id`,`l`.`user_id` AS `owner_id` from (`lost_reports` `l` join `found_reports` `f`) where `l`.`category` = `f`.`category` and `l`.`item_name` like concat('%',substr(`f`.`item_name`,1,5),'%') and `f`.`status` = 'approved' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `system_stats`
--

/*!50001 DROP VIEW IF EXISTS `system_stats`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `system_stats` AS select (select count(0) from `users`) AS `total_users`,(select count(0) from `users` where `users`.`role` = 'admin') AS `total_admins`,(select count(0) from `users` where `users`.`role` = 'user') AS `total_regular_users`,(select count(0) from `lost_reports`) AS `total_lost_reports`,(select count(0) from `found_reports`) AS `total_found_reports`,(select count(0) from `found_reports` where `found_reports`.`status` = 'pending') AS `pending_approvals`,(select count(0) from `claims`) AS `total_claims`,(select count(0) from `claims` where `claims`.`status` = 'pending') AS `pending_claims`,(select count(0) from `claims` where `claims`.`status` = 'approved') AS `approved_claims`,(select count(0) from `deleted_items`) AS `deleted_items_count` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-03 22:38:26
