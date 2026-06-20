-- Snaply — schema database (MySQL 8 / MariaDB 10.4+)
-- Eseguire una volta: mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS snaply
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE snaply;

-- Organizzatori (gli "utenti" autenticati dell'app)
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(255)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Eventi creati dagli organizzatori
CREATE TABLE IF NOT EXISTS events (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  description TEXT NULL,
  event_date  DATE NULL,
  slug         VARCHAR(40) NOT NULL UNIQUE,
  cover_color  VARCHAR(7) NOT NULL DEFAULT '#6C5CE7',
  cover_image  VARCHAR(255) NULL,
  avatar_image VARCHAR(255) NULL,
  avatar_emoji VARCHAR(16) NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_events_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_events_user (user_id)
) ENGINE=InnoDB;

-- Foto e video caricati dagli invitati (pubblico, no auth)
CREATE TABLE IF NOT EXISTS photos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id      INT UNSIGNED NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  thumb_path    VARCHAR(255) NULL,
  file_type     ENUM('image','video') NOT NULL,
  original_name VARCHAR(255) NULL,
  uploader_name VARCHAR(100) NULL,
  size_bytes    INT UNSIGNED NULL,
  likes         INT UNSIGNED NOT NULL DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photos_event
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  INDEX idx_photos_event (event_id)
) ENGINE=InnoDB;
