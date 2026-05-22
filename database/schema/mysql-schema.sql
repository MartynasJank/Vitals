/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cowrie_commands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cowrie_commands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cowrie_session_id` bigint unsigned NOT NULL,
  `input` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cowrie_commands_cowrie_session_id_index` (`cowrie_session_id`),
  CONSTRAINT `cowrie_commands_cowrie_session_id_foreign` FOREIGN KEY (`cowrie_session_id`) REFERENCES `cowrie_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cowrie_downloads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cowrie_downloads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cowrie_session_id` bigint unsigned NOT NULL,
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cowrie_downloads_cowrie_session_id_index` (`cowrie_session_id`),
  CONSTRAINT `cowrie_downloads_cowrie_session_id_foreign` FOREIGN KEY (`cowrie_session_id`) REFERENCES `cowrie_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cowrie_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cowrie_logins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cowrie_session_id` bigint unsigned NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_success` tinyint(1) NOT NULL DEFAULT '1',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cowrie_logins_cowrie_session_id_foreign` (`cowrie_session_id`),
  CONSTRAINT `cowrie_logins_cowrie_session_id_foreign` FOREIGN KEY (`cowrie_session_id`) REFERENCES `cowrie_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cowrie_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cowrie_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_id` bigint unsigned NOT NULL,
  `session` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` timestamp NULL DEFAULT NULL,
  `duration_seconds` decimal(8,2) DEFAULT NULL,
  `is_interesting` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `cowrie_sessions_session_unique` (`session`),
  KEY `cowrie_sessions_ip_id_index` (`ip_id`),
  KEY `cowrie_sessions_is_interesting_index` (`is_interesting`),
  CONSTRAINT `cowrie_sessions_ip_id_foreign` FOREIGN KEY (`ip_id`) REFERENCES `threat_ips` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credentials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hit_count` int unsigned NOT NULL DEFAULT '1',
  `first_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pair` (`username`,`password`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `captured_at` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `malware_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `malware_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sha256` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size_bytes` bigint unsigned NOT NULL,
  `file_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_text` tinyint(1) NOT NULL DEFAULT '0',
  `elf_arch` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `elf_bits` tinyint unsigned DEFAULT NULL,
  `is_upx` tinyint(1) NOT NULL DEFAULT '0',
  `entropy` float DEFAULT NULL,
  `malware_family` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vt_status` enum('pending','found','not_found','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `vt_malicious` int unsigned DEFAULT NULL,
  `vt_total` int unsigned DEFAULT NULL,
  `vt_threat_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vt_threat_categories` json DEFAULT NULL,
  `vt_family_labels` json DEFAULT NULL,
  `vt_last_checked_at` timestamp NULL DEFAULT NULL,
  `vt_permalink` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_seen_at` timestamp NOT NULL,
  `last_seen_at` timestamp NOT NULL,
  `seen_count` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `malware_files_sha256_unique` (`sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `malware_strings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `malware_strings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `malware_file_id` bigint unsigned NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `malware_strings_malware_file_id_index` (`malware_file_id`),
  CONSTRAINT `malware_strings_malware_file_id_foreign` FOREIGN KEY (`malware_file_id`) REFERENCES `malware_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nginx_hits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nginx_hits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_id` bigint unsigned NOT NULL,
  `path` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_code` smallint unsigned DEFAULT NULL,
  `user_agent` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referer` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_type` enum('env_probe','wp_admin','git_exposure','phpmyadmin','log4shell','spring_boot','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `nginx_hits_ip_id_foreign` (`ip_id`),
  KEY `nginx_hits_scan_type_index` (`scan_type`),
  KEY `nginx_hits_timestamp_index` (`timestamp`),
  CONSTRAINT `nginx_hits_ip_id_foreign` FOREIGN KEY (`ip_id`) REFERENCES `threat_ips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cpu_percent` double NOT NULL,
  `ram_used_mb` int NOT NULL,
  `ram_total_mb` int NOT NULL,
  `disk_used_gb` double NOT NULL,
  `disk_total_gb` double NOT NULL,
  `recorded_at` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `site_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_checks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('up','down') COLLATE utf8mb4_unicode_ci NOT NULL,
  `response_ms` int DEFAULT NULL,
  `status_code` int DEFAULT NULL,
  `checked_at` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ssh_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ssh_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_id` bigint unsigned NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attempt` (`ip_id`,`timestamp`),
  KEY `ssh_attempts_timestamp_index` (`timestamp`),
  CONSTRAINT `ssh_attempts_ip_id_foreign` FOREIGN KEY (`ip_id`) REFERENCES `threat_ips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `threat_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `threat_ips` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(8,5) DEFAULT NULL,
  `lon` decimal(8,5) DEFAULT NULL,
  `org` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_proxy` tinyint(1) NOT NULL DEFAULT '0',
  `is_vpn` tinyint(1) NOT NULL DEFAULT '0',
  `is_tor` tinyint(1) NOT NULL DEFAULT '0',
  `total_hits` int unsigned NOT NULL DEFAULT '1',
  `first_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `threat_ips_ip_unique` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_04_30_220249_create_log_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_04_30_220249_create_resource_snapshots_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_04_30_220249_create_site_checks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_05_05_000001_create_threat_ips_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_05_05_000002_create_threat_ssh_attempts_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_05_05_000003_create_threat_nginx_hits_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_05_05_000004_create_threat_credentials_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_05_05_194956_increase_asn_column_in_threat_ips_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_05_05_200234_add_geo_and_org_to_threat_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_05_06_000001_create_cowrie_sessions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_05_06_000002_create_cowrie_logins_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_05_06_000003_create_cowrie_commands_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_05_06_000004_create_cowrie_downloads_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_05_06_195058_add_is_success_to_cowrie_logins_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_05_09_230244_add_is_interesting_to_cowrie_sessions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_05_11_000001_create_malware_files_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_05_11_123145_create_malware_strings_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_05_11_134019_add_analysis_columns_to_malware_files_table',3);
