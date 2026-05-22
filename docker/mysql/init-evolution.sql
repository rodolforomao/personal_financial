CREATE DATABASE IF NOT EXISTS evolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON evolution.* TO 'financial'@'%';
FLUSH PRIVILEGES;

USE evolution;

-- Evolution v2.2.3 (imagem atendai): migração MySQL incompleta sem wavoipToken
ALTER TABLE Setting ADD COLUMN IF NOT EXISTS wavoipToken VARCHAR(100) NULL AFTER syncFullHistory;
