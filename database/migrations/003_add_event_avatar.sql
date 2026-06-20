-- Migration: avatar dell'evento (immagine dedicata e/o emoji a tema).
USE snaply;

ALTER TABLE events
  ADD COLUMN avatar_image VARCHAR(255) NULL AFTER cover_image,
  ADD COLUMN avatar_emoji VARCHAR(16) NULL AFTER avatar_image;
