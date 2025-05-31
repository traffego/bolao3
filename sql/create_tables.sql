-- Criar banco de dados se não existir
CREATE DATABASE IF NOT EXISTS bolao3;
USE bolao3;

-- Criar tabela jogador
CREATE TABLE IF NOT EXISTS jogador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    cpf VARCHAR(14) UNIQUE,
    data_cadastro DATETIME NOT NULL,
    ultimo_login DATETIME,
    pagamento_confirmado TINYINT(1) DEFAULT 0,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    UNIQUE KEY uk_email (email),
    UNIQUE KEY uk_cpf (cpf)
); 