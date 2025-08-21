-- EXEMPLO DO INSERT GERADO PELA FUNÇÃO dbInsert PARA INSERIR UM NOVO JOGADOR
-- Este é o SQL que é executado quando um usuário se cadastra

-- Estrutura da função dbInsert:
-- INSERT INTO {tabela} ({colunas}) VALUES ({placeholders})

-- Para a tabela 'jogador', o INSERT fica assim:
INSERT INTO jogador (
    nome, 
    email, 
    senha, 
    telefone, 
    data_cadastro, 
    status, 
    codigo_afiliado, 
    ref_indicacao, 
    afiliado_ativo
) VALUES (
    ?, -- nome do usuário
    ?, -- email do usuário  
    ?, -- senha hasheada
    ?, -- telefone
    ?, -- data atual (Y-m-d H:i:s)
    'ativo', -- status sempre ativo
    ?, -- código de afiliado único gerado
    ?, -- código do afiliado que indicou (ou NULL)
    ? -- 'ativo' se veio de link de afiliado, 'inativo' se cadastro direto
);

-- EXEMPLO PRÁTICO:
-- Se um usuário se cadastrar via link: https://seusite.com/cadastro.php?ref=ABC123
-- O INSERT seria algo como:
/*
INSERT INTO jogador (
    nome, email, senha, telefone, data_cadastro, status, 
    codigo_afiliado, ref_indicacao, afiliado_ativo
) VALUES (
    'João Silva',
    'joao@email.com', 
    '$2y$10$hashedpassword...',
    '11999999999',
    '2024-01-20 15:30:45',
    'ativo',
    'JOA456', -- código único gerado para João
    'ABC123', -- código do afiliado que indicou
    'ativo' -- João vira afiliado automaticamente
);
*/

-- LOCALIZAÇÃO NO CÓDIGO:
-- Arquivo: cadastro.php
-- Linha: 95 - dbInsert('jogador', $userData);
-- Função dbInsert: config/database.php linha 97-110

-- CAMPOS IMPORTANTES PARA AFILIAÇÃO:
-- codigo_afiliado: Código único do novo usuário (para ele indicar outros)
-- ref_indicacao: Código de quem indicou este usuário (pode ser NULL)
-- afiliado_ativo: 'ativo' se veio de link, 'inativo' se cadastro direto