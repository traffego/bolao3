# Bolão Vitimba - Sistema de Bolões para Futebol

Um sistema completo para gerenciar bolões de futebol, permitindo a criação de bolões, participação de jogadores, registro de palpites e acompanhamento de resultados.

## Funcionalidades

- **Painel de Administração**: Controle total do sistema
- **Área do Jogador**: Participação em bolões e registro de palpites
- **Gestão de Bolões**: Criação, edição e gerenciamento de bolões
- **Gerenciamento de Jogos**: Cadastro de jogos, resultados e atualizações
- **Sistema de Palpites**: Interface intuitiva para apostas
- **Ranking Automático**: Cálculo automático de pontuações e classificação
- **Sistema de Pagamentos**: Controle de pagamentos dos participantes
- **Área de Afiliados**: Sistema de indicações e comissões

## Requisitos gerais

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache, Nginx)
- Extensões PHP: mysqli, json, mbstring

## Instalação

1. Clone o repositório para seu ambiente local ou servidor:
   ```
   git clone https://github.com/seu-usuario/bolao-football.git
   ```

2. Configure seu servidor web para apontar para o diretório raiz do projeto

3. Crie um banco de dados MySQL vazio ou use um existente

4. Execute o script de setup para criar todas as tabelas e inserir dados de demonstração:
   ```
   cd bolao-football/database
   php setup_demo.php
   ```

5. Configure as credenciais de banco de dados no arquivo `config/config.php` (se necessário)

6. Acesse o sistema pelo navegador

## Credenciais de Acesso

### Administrador
- Email: admin@bolao.com
- Senha: admin123

### Jogadores (Todos usam a mesma senha)
- Email: joao@email.com, maria@email.com, carlos@email.com, etc.
- Senha: 123456

## Estrutura do Banco de Dados

O sistema utiliza as seguintes tabelas:

- `administrador`: Administradores do sistema
- `jogador`: Jogadores/usuários registrados
- `boloes`: Bolões disponíveis no sistema
- `jogos`: Jogos cadastrados em cada bolão
- `participacoes`: Relação entre jogadores e bolões
- `palpites`: Palpites dos jogadores para cada jogo
- `resultados`: Resultados oficiais dos jogos
- `pagamentos`: Controle de pagamentos realizados
- `ranking`: Classificação dos jogadores por bolão
- `afiliados`: Sistema de afiliados/indicações
- `configuracoes`: Configurações gerais do sistema

## Uso

### Fluxo de Administração
1. Criar um novo bolão
2. Adicionar jogos ao bolão
3. Convidar jogadores ou deixar o bolão aberto para participação
4. Atualizar resultados dos jogos
5. Calcular pontuações e acompanhar o ranking

### Fluxo do Jogador
1. Criar uma conta ou fazer login
2. Participar dos bolões disponíveis
3. Registrar palpites para os jogos
4. Acompanhar resultados e ranking

## Customização

O sistema pode ser facilmente customizado:

- Ajuste as regras de pontuação em `configuracoes`
- Modifique os templates em `templates/`
- Adicione novos recursos conforme necessário

## Licença

Este projeto está licenciado sob a Licença MIT - veja o arquivo LICENSE para detalhes.

## Suporte

Para suporte, entre em contato através do email: [seu-email@exemplo.com] "# bolao3" 
