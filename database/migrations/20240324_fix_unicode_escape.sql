-- Corrigir caracteres unicode escapados na tabela configuracoes
UPDATE configuracoes 
SET valor = JSON_UNQUOTE(valor)
WHERE valor LIKE '%\\u%';

-- Corrigir caracteres especiais na descrição
UPDATE configuracoes 
SET descricao = REPLACE(descricao, 'N??mero', 'Número')
WHERE descricao LIKE '%N??mero%';

UPDATE configuracoes 
SET descricao = REPLACE(descricao, 'padr??o', 'padrão')
WHERE descricao LIKE '%padr??o%';

UPDATE configuracoes 
SET descricao = REPLACE(descricao, 'bol??o', 'bolão')
WHERE descricao LIKE '%bol??o%';

UPDATE configuracoes 
SET descricao = REPLACE(descricao, 'confirma????o', 'confirmação')
WHERE descricao LIKE '%confirma????o%';

UPDATE configuracoes 
SET descricao = REPLACE(descricao, 'usu??rios', 'usuários')
WHERE descricao LIKE '%usu??rios%';

-- Atualizar valores específicos que precisam de correção
UPDATE configuracoes 
SET valor = '"Bolão de Futebol"'
WHERE nome_configuracao = 'site_name';

UPDATE configuracoes 
SET valor = '"O melhor sistema de bolões de futebol!"'
WHERE nome_configuracao = 'site_description';

-- Remover aspas extras em valores que não são JSON
UPDATE configuracoes 
SET valor = TRIM(BOTH '"' FROM valor)
WHERE categoria IN ('geral', 'pontuacao') 
AND nome_configuracao NOT IN ('site_name', 'site_description', 'moeda')
AND valor LIKE '"%"';

-- Garantir que os valores JSON permaneçam válidos
UPDATE configuracoes 
SET valor = REPLACE(valor, '\\/', '/')
WHERE valor LIKE '%\\/%'; 