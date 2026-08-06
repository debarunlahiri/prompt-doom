CREATE DATABASE IF NOT EXISTS prompt_doom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE prompt_doom;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  avatar_url VARCHAR(500) NULL,
  status ENUM(
    'pending_verification',
    'active',
    'blocked',
    'suspended',
    'deletion_pending'
  ) NOT NULL DEFAULT 'active',
  locale VARCHAR(16) NOT NULL DEFAULT 'en',
  timezone VARCHAR(80) NOT NULL DEFAULT 'UTC',
  failed_login_count INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  password_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  terms_accepted_at DATETIME NULL,
  privacy_accepted_at DATETIME NULL,
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role ENUM('admin', 'super_admin') NOT NULL DEFAULT 'admin',
  status ENUM('active', 'blocked', 'suspended') NOT NULL DEFAULT 'active',
  failed_login_count INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  password_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  mfa_enabled BOOLEAN NOT NULL DEFAULT FALSE,
  mfa_secret_ciphertext TEXT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS user_refresh_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  admin_id INT UNSIGNED NULL,
  jti VARCHAR(64) NOT NULL UNIQUE,
  family_id CHAR(36) NOT NULL,
  session_id CHAR(36) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  revoked_reason VARCHAR(255) NULL,
  device_id VARCHAR(190) NULL,
  user_agent TEXT NULL,
  ip_hash CHAR(64) NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (admin_id),
  INDEX (expires_at),
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  attempt_count INT NOT NULL DEFAULT 0,
  request_ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  parent_id INT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (parent_id),
  FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS tags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(240) NOT NULL UNIQUE,
  category_id INT UNSIGNED NULL,
  image_url VARCHAR(500) NOT NULL,
  image_key VARCHAR(500) NOT NULL,
  thumbnail_url VARCHAR(500) NOT NULL,
  thumbnail_key VARCHAR(500) NOT NULL,
  ai_model VARCHAR(100) NULL,
  description TEXT NULL,
  generation_metadata JSON NULL,
  status ENUM('draft', 'published', 'unpublished') NOT NULL DEFAULT 'draft',
  moderation_status ENUM(
    'pending',
    'approved',
    'rejected',
    'review_required'
  ) NOT NULL DEFAULT 'pending',
  moderation_notes TEXT NULL,
  scheduled_at DATETIME NULL,
  view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  copy_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  version INT NOT NULL DEFAULT 1,
  published_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX (category_id),
  INDEX (status),
  FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES admin_users (id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS image_prompts (
  image_id INT UNSIGNED PRIMARY KEY,
  main_prompt LONGTEXT NOT NULL,
  negative_prompt LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS image_tags (
  image_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (image_id, tag_id),
  FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS user_favorites (
  user_id INT UNSIGNED NOT NULL,
  image_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, image_id),
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS prompt_view_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  image_id INT UNSIGNED NOT NULL,
  viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  view_count INT UNSIGNED NOT NULL DEFAULT 1,
  copy_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_copied_at DATETIME NULL,
  UNIQUE KEY unique_user_image (user_id, image_id),
  INDEX (user_id, viewed_at),
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS analytics_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id CHAR(36) NOT NULL UNIQUE,
  user_id INT UNSIGNED NULL,
  image_id INT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  session_id VARCHAR(100) NULL,
  metadata JSON NULL,
  platform VARCHAR(80) NULL,
  app_version VARCHAR(40) NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (event_type),
  INDEX (created_at),
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS content_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  image_id INT UNSIGNED NOT NULL,
  reason ENUM(
    'sexual',
    'violent',
    'hateful',
    'copyright',
    'misleading',
    'other'
  ) NOT NULL,
  details VARCHAR(1000) NULL,
  status ENUM('pending', 'reviewed', 'dismissed', 'actioned') NOT NULL DEFAULT 'pending',
  reviewed_by INT UNSIGNED NULL,
  assigned_to INT UNSIGNED NULL,
  priority INT NOT NULL DEFAULT 0,
  resolution_note TEXT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_report (user_id, image_id),
  INDEX (status, priority, created_at),
  INDEX (assigned_to, status),
  FOREIGN KEY (user_id) REFERENCES users (id),
  FOREIGN KEY (image_id) REFERENCES images (id),
  FOREIGN KEY (reviewed_by) REFERENCES admin_users (id),
  FOREIGN KEY (assigned_to) REFERENCES admin_users (id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ad_settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  enabled BOOLEAN NOT NULL DEFAULT TRUE,
  show_after_clicks INT NOT NULL DEFAULT 5,
  min_interval_seconds INT NOT NULL DEFAULT 120,
  max_ads_per_session INT NOT NULL DEFAULT 3,
  version INT NOT NULL DEFAULT 1,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by) REFERENCES admin_users (id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ad_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id CHAR(36) NOT NULL UNIQUE,
  user_id INT UNSIGNED NULL,
  session_id VARCHAR(100) NOT NULL,
  event_type ENUM(
    'displayed',
    'closed',
    'clicked',
    'failed',
    'skipped'
  ) NOT NULL,
  provider VARCHAR(80) NULL,
  placement VARCHAR(80) NULL,
  metadata JSON NULL,
  image_click_sequence INT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (session_id, created_at),
  INDEX (event_type, occurred_at),
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS admin_rbac_roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  is_system BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS admin_permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(190) NOT NULL UNIQUE,
  description TEXT NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS admin_user_roles (
  admin_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (admin_id, role_id),
  FOREIGN KEY (admin_id) REFERENCES admin_users (id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES admin_rbac_roles (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS admin_role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES admin_rbac_roles (id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES admin_permissions (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS prompt_revisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  image_id INT UNSIGNED NOT NULL,
  revision INT NOT NULL,
  main_prompt LONGTEXT NOT NULL,
  negative_prompt LONGTEXT NULL,
  change_note VARCHAR(500) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_revision (image_id, revision),
  INDEX (image_id, created_at),
  FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES admin_users (id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS image_assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) NOT NULL UNIQUE,
  image_id INT UNSIGNED NOT NULL,
  kind ENUM('original', 'thumbnail') NOT NULL,
  bucket VARCHAR(190) NOT NULL,
  object_key VARCHAR(500) NOT NULL UNIQUE,
  public_url VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  byte_size BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  width INT NULL,
  height INT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY unique_asset_kind (image_id, kind),
  INDEX (deleted_at),
  FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES admin_users (id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS idempotency_keys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  idempotency_key VARCHAR(190) NOT NULL,
  user_id INT UNSIGNED NULL,
  scope VARCHAR(190) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  response_code INT NULL,
  response_body JSON NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_key_scope (idempotency_key, scope),
  INDEX (expires_at),
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS outbox_events (
  id CHAR(36) PRIMARY KEY,
  aggregate_type VARCHAR(190) NOT NULL,
  aggregate_id VARCHAR(190) NOT NULL,
  event_type VARCHAR(190) NOT NULL,
  payload JSON NOT NULL,
  status ENUM('pending', 'processing', 'published', 'failed') NOT NULL DEFAULT 'pending',
  attempt_count INT NOT NULL DEFAULT 0,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status, available_at),
  INDEX (aggregate_type, aggregate_id)
) ENGINE = InnoDB;

INSERT INTO
  ad_settings (id)
VALUES
  (1)
ON DUPLICATE KEY UPDATE
  id = id;
