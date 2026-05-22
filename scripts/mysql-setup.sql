-- Execute com seu utilizador MySQL admin (ex.: mysql -u root -p < scripts/mysql-setup.sql)
CREATE DATABASE IF NOT EXISTS financial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'financial'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON financial.* TO 'financial'@'localhost';
FLUSH PRIVILEGES;
