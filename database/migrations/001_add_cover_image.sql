-- Migration: aggiunge la copertina personalizzata agli eventi.
-- Eseguire su database già esistenti (chi crea da schema.sql ha già la colonna).
USE snaply;

ALTER TABLE events
  ADD COLUMN cover_image VARCHAR(255) NULL AFTER cover_color;
