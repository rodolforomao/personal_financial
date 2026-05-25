-- Correção para Evolution API v2.2.3 + MySQL (migração wavoipToken ausente na imagem atendai)
-- Uso: mysql "$EVOLUTION_DB_DATABASE" < docker/mysql/fix-evolution-schema.sql
ALTER TABLE Setting ADD COLUMN IF NOT EXISTS wavoipToken VARCHAR(100) NULL AFTER syncFullHistory;
