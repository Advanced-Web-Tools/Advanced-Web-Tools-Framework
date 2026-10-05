-- Add the storage table required by the refactored installer.
CREATE TABLE IF NOT EXISTS `awt_storage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `path` text NOT NULL,
  `url` text DEFAULT NULL,
  `size` bigint DEFAULT NULL,
  `middleware` text DEFAULT NULL,
  `lastModified` bigint DEFAULT NULL,
  `ownerId` int(11) DEFAULT NULL,
  `ownerType` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ownerId` (`ownerId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
