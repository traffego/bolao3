-- Create API Keys Table for Enhanced Security
-- This table stores API keys with proper security features and management capabilities

CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_name` varchar(100) NOT NULL COMMENT 'Human-readable name for the API key',
  `api_key` varchar(64) NOT NULL COMMENT 'The actual API key (hashed)',
  `key_prefix` varchar(16) NOT NULL COMMENT 'Visible prefix for identification',
  `permissions` JSON DEFAULT NULL COMMENT 'JSON array of allowed permissions/scopes',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether the key is active',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created the key',
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Last time the key was used',
  `last_used_ip` varchar(45) DEFAULT NULL COMMENT 'IP address of last usage',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Optional expiration date',
  `usage_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of times the key has been used',
  `rate_limit` int(11) DEFAULT NULL COMMENT 'Max requests per hour (NULL = no limit)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_api_key` (`api_key`),
  UNIQUE KEY `unique_key_name` (`key_name`),
  KEY `idx_active_keys` (`is_active`, `expires_at`),
  KEY `idx_key_prefix` (`key_prefix`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API Keys for webhook and system access';

-- Default API keys will be inserted via PHP migration script for better security

-- Create audit log table for API key usage
CREATE TABLE IF NOT EXISTS `api_key_usage_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key_id` int(11) NOT NULL,
  `endpoint` varchar(255) NOT NULL COMMENT 'Which endpoint was accessed',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether the request was successful',
  `error_message` varchar(500) DEFAULT NULL COMMENT 'Error message if request failed',
  `request_data` JSON DEFAULT NULL COMMENT 'Additional request information',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api_key_id` (`api_key_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_endpoint` (`endpoint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API Key usage audit log';

-- Add foreign key constraint
ALTER TABLE `api_key_usage_log` 
ADD CONSTRAINT `fk_api_key_usage_log_api_key` 
FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;