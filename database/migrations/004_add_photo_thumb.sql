-- Migration: miniatura (thumbnail) generata lato server per le foto.
USE snaply;

ALTER TABLE photos
  ADD COLUMN thumb_path VARCHAR(255) NULL AFTER file_path;
