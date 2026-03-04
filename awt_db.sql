-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 04, 2025 at 01:05 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `awt_development`
--

-- --------------------------------------------------------

--
-- Table structure for table `awt_admin`
--

DROP TABLE IF EXISTS `awt_admin`;
CREATE TABLE IF NOT EXISTS `awt_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `profile_picture` varchar(255) DEFAULT 'awt_data/media/icons/circle-user-regular.svg',
  `last_logged_ip` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `permission_level` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `awt_data`
--

DROP TABLE IF EXISTS `awt_data`;
CREATE TABLE IF NOT EXISTS `awt_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ownerType` varchar(255) NOT NULL,
  `ownerName` varchar(255) DEFAULT NULL,
  `ownerId` int(11) DEFAULT NULL,
  `dataType` varchar(255) NOT NULL,
  `dataName` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ownerId` (`ownerId`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `awt_data`
--

TRUNCATE TABLE `awt_data`;
--
-- Dumping data for table `awt_data`
--

-- --------------------------------------------------------

--
-- Table structure for table `awt_package`
--

CREATE TABLE IF NOT EXISTS `awt_package` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) DEFAULT NULL,
  `installed_by` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` text DEFAULT NULL,
  `preview_image` text DEFAULT NULL,
  `license` varchar(255) DEFAULT NULL,
  `license_url` varchar(255) DEFAULT NULL,
  `author` varchar(128) DEFAULT NULL,
  `version` varchar(128) NOT NULL,
  `minimum_awt_version` varchar(128) NOT NULL,
  `maximum_awt_version` varchar(128) DEFAULT NULL,
  `type` tinyint(4) NOT NULL,
  `system_package` tinyint(4) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `installation_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `store_id` (`store_id`),
  KEY `installed_by` (`installed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `awt_setting`
--

DROP TABLE IF EXISTS `awt_setting`;
CREATE TABLE IF NOT EXISTS `awt_setting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value_type` varchar(16) NOT NULL DEFAULT 'text',
  `value` varchar(255) NOT NULL,
  `required_permission_level` int(11) NOT NULL DEFAULT 0,
  `category` varchar(32) DEFAULT 'Miscellaneous',
  PRIMARY KEY (`id`),
  KEY `package_id` (`package_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `awt_setting`
--

TRUNCATE TABLE `awt_setting`;
--
-- Dumping data for table `awt_setting`
--

INSERT INTO `awt_setting` (`id`, `package_id`, `name`, `value_type`, `value`, `required_permission_level`, `category`) VALUES
(1, 1, 'Website Name', 'text', '', 0, 'General'),
(2, 1, 'Hostname Path', 'text', '', 0, 'General'),
(3, 1, 'Use Packages', 'boolean', 'true', 0, 'General'),
(4, 1, 'Session HTTPS Only', 'boolean', 'false', 0, 'Session'),
(5, 1, 'Session HTTP Only', 'boolean', 'true', 0, 'Session'),
(6, 1, 'Session ID Regeneration Time', 'number', '900', 0, 'Session'),
(7, 1, 'Session SameSite', 'boolean', 'true', 0, 'Session'),
(8, 1, 'Contact Email', 'text', '', 0, 'General'),
(9, 1, 'Phone Number', 'text', '', 0, 'General'),
(10, 1, 'Address', 'text', '', 0, 'General');

-- --------------------------------------------------------

--
-- Table structure for table `awt_table`
--

DROP TABLE IF EXISTS `awt_table`;
CREATE TABLE IF NOT EXISTS `awt_table` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `creation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `creator` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `creator` (`creator`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `awt_table`
--

TRUNCATE TABLE `awt_table`;
--
-- Dumping data for table `awt_table`
--

INSERT INTO `awt_table` (`id`, `name`, `creation_date`, `creator`) VALUES
(1, 'awt_admin', '2024-12-24 21:42:51', 1),
(2, 'awt_data', '2024-12-24 21:42:51', 1),
(3, 'awt_package', '2024-12-24 21:42:51', 1),
(4, 'awt_setting', '2024-12-24 21:42:51', 1),
(5, 'awt_table', '2024-12-24 21:47:27', 1),
(6, 'awt_table_structure', '2024-12-24 21:47:40', 1);

-- --------------------------------------------------------

--
-- Table structure for table `awt_table_structure`
--

DROP TABLE IF EXISTS `awt_table_structure`;
CREATE TABLE IF NOT EXISTS `awt_table_structure` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_id` int(11) NOT NULL,
  `column_name` varchar(255) NOT NULL,
  `column_type` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `table_id` (`table_id`)
) ENGINE=InnoDB AUTO_INCREMENT=232 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Truncate table before insert `awt_table_structure`
--

TRUNCATE TABLE `awt_table_structure`;
--
-- Dumping data for table `awt_table_structure`
--

INSERT INTO `awt_table_structure` (`id`, `table_id`, `column_name`, `column_type`) VALUES
(163, 1, 'id', 'int'),
(164, 1, 'email', 'varchar(255)'),
(165, 1, 'username', 'varchar(255)'),
(166, 1, 'firstname', 'varchar(255)'),
(167, 1, 'lastname', 'varchar(255)'),
(168, 1, 'profile_picture', 'varchar(255)'),
(169, 1, 'last_logged_ip', 'varchar(255)'),
(170, 1, 'password', 'varchar(255)'),
(171, 1, 'token', 'varchar(255)'),
(172, 1, 'permission_level', 'int'),
(173, 2, 'id', 'int'),
(174, 2, 'ownerType', 'varchar(255)'),
(175, 2, 'ownerName', 'varchar(255)'),
(176, 2, 'ownerId', 'int'),
(177, 2, 'dataType', 'varchar(255)'),
(178, 2, 'dataName', 'varchar(255)'),
(179, 3, 'id', 'int'),
(180, 3, 'store_id', 'int'),
(181, 3, 'installed_by', 'int'),
(182, 3, 'name', 'varchar(255)'),
(183, 3, 'description', 'text'),
(184, 3, 'icon', 'text'),
(185, 3, 'preview_image', 'text'),
(186, 3, 'license', 'varchar(255)'),
(187, 3, 'license_url', 'varchar(255)'),
(188, 3, 'author', 'varchar(128)'),
(189, 3, 'version', 'varchar(128)'),
(190, 3, 'minimum_awt_version', 'varchar(128)'),
(191, 3, 'maximum_awt_version', 'varchar(128)'),
(192, 3, 'type', 'tinyint'),
(193, 3, 'system_package', 'tinyint'),
(194, 3, 'status', 'tinyint'),
(195, 3, 'installation_date', 'datetime'),
(196, 4, 'id', 'int'),
(197, 4, 'package_id', 'int'),
(198, 4, 'name', 'varchar(255)'),
(199, 4, 'value_type', 'varchar(16)'),
(200, 4, 'value', 'varchar(255)'),
(201, 4, 'required_permission_level', 'int'),
(202, 4, 'category', 'varchar(32)'),
(227, 5, 'id', 'int'),
(228, 5, 'name', 'varchar(255)'),
(229, 5, 'creator', 'int'),
(230, 5, 'creation_date', 'timestamp'),
(231, 6, 'id', 'int'),
(225, 6, 'column_name', 'varchar(255)'),
(226, 6, 'column_type', 'varchar(32)');
-- --------------------------------------------------------





--
-- Constraints for table `awt_data`
--
ALTER TABLE `awt_data`
  ADD CONSTRAINT `awt_data_ibfk_1` FOREIGN KEY (`ownerId`) REFERENCES `awt_package` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `awt_package`
--
ALTER TABLE `awt_package`
  ADD CONSTRAINT `awt_package_ibfk_1` FOREIGN KEY (`installed_by`) REFERENCES `awt_admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `awt_setting`
--
ALTER TABLE `awt_setting`
  ADD CONSTRAINT `awt_setting_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `awt_package` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `awt_table`
--
ALTER TABLE `awt_table`
  ADD CONSTRAINT `awt_table_ibfk_1` FOREIGN KEY (`creator`) REFERENCES `awt_package` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `awt_table_structure`
--
ALTER TABLE `awt_table_structure`
  ADD CONSTRAINT `awt_table_structure_ibfk_1` FOREIGN KEY (`table_id`) REFERENCES `awt_table` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;


COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
