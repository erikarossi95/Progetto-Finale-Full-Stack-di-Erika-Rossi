-- Migration: aggiunge il conteggio dei "cuori" alle foto/video.
USE snaply;

ALTER TABLE photos
  ADD COLUMN likes INT UNSIGNED NOT NULL DEFAULT 0 AFTER size_bytes;
