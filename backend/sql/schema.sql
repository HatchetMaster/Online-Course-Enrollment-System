CREATE DATABASE IF NOT EXISTS OCES CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE OCES;

CREATE TABLE IF NOT EXISTS tblStudents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  first_name_enc VARCHAR(255) NOT NULL,
  last_name_enc VARCHAR(255) NOT NULL,
  username_enc VARCHAR(255) NOT NULL,
  username_hash CHAR(64) NOT NULL,
  email_enc VARCHAR(255) NOT NULL,
  email_hash CHAR(64) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  tfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  totp_secret VARBINARY(512) NULL,
  last_login DATETIME NULL,
  failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_students_username_hash (username_hash),
  UNIQUE KEY uq_students_email_hash (email_hash),
  INDEX idx_students_email_hash (email_hash),
  INDEX idx_students_username_hash (username_hash),
  INDEX idx_students_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
