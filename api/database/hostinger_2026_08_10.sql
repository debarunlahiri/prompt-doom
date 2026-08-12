-- Prompt Doom Hostinger production migration
-- Date: 2026-08-10
--
-- TABLES UPDATED
--   images: makes ai_model optional and converts legacy blank values to NULL.
--
-- TABLES CREATED
--   prompt_revisions: created only if it is missing.
--   image_assets: created only if it is missing.
--
-- TABLES DELETED
--   None.
--
-- RUNTIME DELETE BEHAVIOUR (performed by the admin API, not this migration)
--   Deleting a category:
--     1. UPDATE images SET category_id = NULL for the category.
--     2. UPDATE categories SET parent_id = NULL for child categories.
--     3. DELETE the row from categories.
--   Deleting a tag:
--     1. DELETE related rows from image_tags.
--     2. DELETE the row from tags.

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

ALTER TABLE images
  MODIFY ai_model VARCHAR(100) NULL;

UPDATE images
SET ai_model = NULL
WHERE ai_model = '';
