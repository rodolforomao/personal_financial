-- Correção para Evolution API v2.2.3 + MySQL (migração wavoipToken ausente na imagem atendai)
USE evolution;
ALTER TABLE Setting ADD COLUMN IF NOT EXISTS wavoipToken VARCHAR(100) NULL AFTER syncFullHistory;
