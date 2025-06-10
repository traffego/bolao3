-- Adicionar campo status à tabela palpites se ele não existir
SET @dbname = 'bolao';
SET @tablename = 'palpites';
SET @columnname = 'status';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT "Coluna status já existe na tabela palpites"',
  'ALTER TABLE palpites ADD COLUMN status ENUM("pendente", "pago", "cancelado") DEFAULT "pendente" AFTER data_palpite'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists; 