USE prompt_doom;

ALTER TABLE users
  ADD COLUMN google_uid VARCHAR(128) NULL AFTER email,
  ADD UNIQUE INDEX users_google_uid_unique (google_uid);
