# Documentação do Arquivo bolao.php

## Visão Geral

O arquivo `bolao.php` é o coração da funcionalidade de palpites do sistema de bolões. Ele é responsável por exibir um bolão específico, permitir que usuários façam seus palpites e gerenciar todo o fluxo de participação.

## Estrutura do Arquivo

### 1. Inicialização e Includes

```php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
```

**Função:** Carrega as configurações essenciais do sistema, conexão com banco de dados e funções auxiliares.

### 2. Validação de Parâmetros de Entrada

```php
$bolaoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$bolaoSlug = isset($_GET['slug']) ? $_GET['slug'] : '';
```

**Função:** O sistema aceita dois tipos de identificação para o bolão:
- **ID numérico:** `bolao.php?id=123`
- **Slug amigável:** `bolao.php?slug=copa-do-mundo-2024`

**Validação:** Se nenhum parâmetro for fornecido, redireciona para a lista de bolões públicos.

### 3. Busca e Validação do Bolão

```php
if ($bolaoId > 0) {
    $bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ? AND status = 1", [$bolaoId]);
} else {
    $bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE slug = ? AND status = 1", [$bolaoSlug]);
}
```

**Função:** Busca o bolão no banco de dados garantindo que:
- O bolão existe
- Está ativo (status = 1)
- Corresponde ao ID ou slug fornecido

**Tratamento de Erro:** Se o bolão não for encontrado, exibe mensagem de erro e redireciona.

### 4. Controle de Acesso

```php
if ($bolao['publico'] != 1 && !isLoggedIn()) {
    setFlashMessage('warning', 'Este bolão é privado. Faça login para acessá-lo.');
    redirect(APP_URL . '/login.php');
}
```

**Função:** Implementa controle de acesso baseado na configuração do bolão:
- **Bolões públicos:** Qualquer pessoa pode visualizar
- **Bolões privados:** Apenas usuários logados podem acessar

### 5. Sistema de Pagamento e Saldo

```php
$usuarioId = isLoggedIn() ? getCurrentUserId() : 0;
$podeApostar = false;
$saldoInfo = null;

if ($usuarioId) {
    $modeloPagamento = getModeloPagamento();
    
    if ($modeloPagamento === 'conta_saldo') {
        $saldoInfo = verificarSaldoJogador($usuarioId);
        $podeApostar = $saldoInfo['tem_saldo'] && $saldoInfo['saldo_atual'] >= $bolao['valor_participacao'];
    } else {
        $podeApostar = true;
    }
}
```

**Função:** Gerencia dois modelos de pagamento:

#### Modelo "conta_saldo"
- Verifica se o usuário tem saldo suficiente
- Compara saldo atual com valor de participação
- Bloqueia apostas se saldo insuficiente

#### Modelo "por_aposta"
- Permite apostar sempre
- Pagamento é processado após confirmação dos palpites

### 6. Processamento de Dados do Bolão

```php
$jogos = json_decode($bolao['jogos'], true) ?: [];
$campeonatos = json_decode($bolao['campeonatos'], true) ?: [];
```

**Função:** Decodifica dados JSON armazenados no banco:
- **jogos:** Lista completa de jogos com times, datas, logos
- **campeonatos:** Informações dos campeonatos incluídos

**Ordenação:** Os jogos são ordenados cronologicamente:

```php
usort($jogos, function($a, $b) {
    $dateA = isset($a['data_iso']) ? $a['data_iso'] : $a['data'];
    $dateB = isset($b['data_iso']) ? $b['data_iso'] : $b['data'];
    return strtotime($dateA) - strtotime($dateB);
});
```

### 7. Controle de Prazo para Palpites

```php
$prazoEncerrado = false;
$dataLimite = calcularPrazoLimitePalpites($jogos, $bolao['data_limite_palpitar']);

if ($dataLimite) {
    $agora = new DateTime();
    $prazoEncerrado = $agora > $dataLimite;
}
```

**Função:** Calcula automaticamente o prazo limite:
- **Regra:** 5 minutos antes do primeiro jogo
- **Fallback:** Usa data_limite_palpitar se configurada
- **Bloqueio:** Impede novos palpites após o prazo

### 8. Gerenciamento de Palpites

#### 8.1 Recuperação de Palpites Existentes

```php
if ($usuarioId) {
    $participante = dbFetchOne("SELECT id FROM participacoes WHERE bolao_id = ? AND jogador_id = ?", [$bolao['id'], $usuarioId]);
    
    if ($participante) {
        $palpitesUsuario = dbFetchOne("SELECT palpites FROM palpites WHERE bolao_id = ? AND jogador_id = ?", [$bolao['id'], $usuarioId]);
        if ($palpitesUsuario) {
            $palpites = json_decode($palpitesUsuario['palpites'], true) ?: [];
        }
    }
}
```

**Função:** Para usuários logados:
- Verifica se já é participante do bolão
- Recupera palpites salvos anteriormente
- Permite edição até o prazo limite

#### 8.2 Palpites Temporários (Sessão)

```php
else if (isset($_SESSION['palpites_temp'])) {
    if (isset($_SESSION['palpites_temp']['bolao_id']) && $_SESSION['palpites_temp']['bolao_id'] == $bolao['id']) {
        // Restaurar palpites preservados
        $palpitesSessao = [];
        foreach ($_SESSION['palpites_temp'] as $key => $value) {
            if (strpos($key, 'resultado_') === 0) {
                $jogoId = substr($key, strlen('resultado_'));
                $palpitesSessao[$jogoId] = $value;
            }
        }
    }
}
```

**Função:** Para usuários não logados:
- Armazena palpites temporariamente na sessão
- Preserva palpites durante processo de login/cadastro
- Restaura palpites após autenticação

### 9. Processamento de Formulário (POST)

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($prazoEncerrado) {
        $mensagem = ['tipo' => 'danger', 'texto' => 'O prazo para envio de palpites já foi encerrado.'];
    } else {
        // Processar palpites...
    }
}
```

#### 9.1 Coleta de Palpites

```php
$palpitesForm = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'resultado_') === 0) {
        $jogoId = substr($key, strlen('resultado_'));
        $palpitesForm[$jogoId] = $value; // "1" = casa, "0" = empate, "2" = visitante
    }
}
```

**Formato dos Palpites:**
- **"1":** Time da casa vence
- **"0":** Empate
- **"2":** Time visitante vence

#### 9.2 Validação de Completude

```php
if (count($palpitesForm) !== count($jogos)) {
    $mensagem = ['tipo' => 'warning', 'texto' => 'Você precisa dar palpites para todos os jogos.'];
}
```

**Função:** Garante que todos os jogos tenham palpites antes de salvar.

#### 9.3 Fluxo de Autenticação

```php
if (!isLoggedIn()) {
    $_SESSION['palpites_temp'] = ['bolao_id' => $bolao['id'], 'palpites' => $palpitesForm];
    $_SESSION['login_redirect'] = APP_URL . '/bolao.php?slug=' . $bolao['slug'];
    setFlashMessage('info', 'Por favor, faça login para salvar seus palpites.');
    redirect(APP_URL . '/login.php');
}
```

**Função:** Para usuários não logados:
- Salva palpites temporariamente
- Define URL de retorno após login
- Redireciona para página de login

#### 9.4 Verificação de Pagamento

```php
elseif (!$podeApostar) {
    $_SESSION['palpites_temp'] = ['bolao_id' => $bolao['id'], 'palpites' => $palpitesForm];
    setFlashMessage('warning', 'Você precisa efetuar o pagamento para participar do bolão.');
    redirect(APP_URL . '/pagamento.php');
}
```

**Função:** Para usuários sem saldo/pagamento:
- Preserva palpites durante processo de pagamento
- Redireciona para página de pagamento

#### 9.5 Salvamento no Banco de Dados

```php
try {
    // Verificar/criar participação
    $participante = dbFetchOne("SELECT id FROM participacoes WHERE bolao_id = ? AND jogador_id = ?", [$bolao['id'], $usuarioId]);
    
    if (!$participante) {
        dbInsert('participacoes', [
            'bolao_id' => $bolao['id'],
            'jogador_id' => $usuarioId,
            'data_entrada' => date('Y-m-d H:i:s'),
            'status' => 1
        ]);
    }
    
    // Verificar/salvar palpites
    $palpiteExistente = dbFetchOne("SELECT id FROM palpites WHERE bolao_id = ? AND jogador_id = ?", [$bolao['id'], $usuarioId]);
    
    $palpitesData = [
        'bolao_id' => $bolao['id'],
        'jogador_id' => $usuarioId,
        'palpites' => json_encode($palpitesForm),
        'data_palpite' => date('Y-m-d H:i:s')
    ];
    
    if ($palpiteExistente) {
        dbUpdate('palpites', $palpitesData, 'id = ?', [$palpiteExistente['id']]);
    } else {
        dbInsert('palpites', $palpitesData);
    }
} catch (Exception $e) {
    $mensagem = ['tipo' => 'danger', 'texto' => 'Erro ao salvar os palpites: ' . $e->getMessage()];
}
```

**Função:** Processo completo de salvamento:
1. **Participação:** Registra usuário como participante do bolão
2. **Palpites:** Salva/atualiza palpites em formato JSON
3. **Tratamento de Erro:** Captura e exibe erros de banco

## Interface do Usuário

### 1. Layout Responsivo

A interface utiliza Bootstrap com layout em duas colunas:
- **Coluna Esquerda (col-md-4):** Informações do bolão
- **Coluna Direita (col-md-8):** Lista de jogos e palpites

### 2. Card de Informações do Bolão

```php
<div class="card shadow-sm border-0 mobile-info-card">
    <div class="card-header text-white" style="background: var(--gradient-primary)">
        <!-- Cabeçalho com toggle mobile -->
    </div>
    <div class="card-body p-3" id="bolaoInfoContent">
        <!-- Informações detalhadas -->
    </div>
</div>
```

**Características:**
- **Mobile-First:** Colapsível em dispositivos móveis
- **Informações Exibidas:**
  - Nome e descrição do bolão
  - Período de vigência
  - Prazo para palpites com countdown
  - Valor de participação
  - Saldo do usuário (se aplicável)
  - Total de jogos
  - Prêmios disponíveis

### 3. Sistema de Countdown

```php
<?php if (!$prazoEncerrado): ?>
<div class="countdown-compact" data-target="<?= $dataLimite->format('Y-m-d H:i:s') ?>">
    <span class="countdown-text">...</span>
</div>
<?php endif; ?>
```

**Função:** Exibe tempo restante para palpitar em tempo real.

### 4. Lista de Jogos

#### 4.1 Agrupamento por Data

```php
$jogosPorData = [];
foreach ($jogos as $jogo) {
    $dataJogo = !empty($jogo['data_formatada']) ? substr($jogo['data_formatada'], 0, 10) : date('d/m/Y', strtotime($jogo['data']));
    $jogosPorData[$dataJogo][] = $jogo;
}
```

**Função:** Organiza jogos por data para melhor visualização.

#### 4.2 Interface de Palpites

```php
<div class="palpites-buttons btn-group" role="group">
    <!-- Botão Time Casa -->
    <input type="radio" class="btn-check" name="resultado_<?= $jogo['id'] ?>" id="casa_<?= $jogo['id'] ?>" value="1">
    <label class="btn btn-outline-success btn-palpite btn-time" for="casa_<?= $jogo['id'] ?>">
        <img src="<?= $jogo['logo_time_casa'] ?>" alt="<?= $jogo['nome_time_casa'] ?>" class="team-logo-btn">
        <span class="team-name-btn"><?= $jogo['nome_time_casa'] ?></span>
    </label>
    
    <!-- Botão Empate -->
    <input type="radio" class="btn-check" name="resultado_<?= $jogo['id'] ?>" id="empate_<?= $jogo['id'] ?>" value="0">
    <label class="btn btn-outline-warning btn-palpite" for="empate_<?= $jogo['id'] ?>">
        <i class="bi bi-x-lg"></i>
    </label>
    
    <!-- Botão Time Visitante -->
    <input type="radio" class="btn-check" name="resultado_<?= $jogo['id'] ?>" id="fora_<?= $jogo['id'] ?>" value="2">
    <label class="btn btn-outline-danger btn-palpite btn-time" for="fora_<?= $jogo['id'] ?>">
        <img src="<?= $jogo['logo_time_visitante'] ?>" alt="<?= $jogo['nome_time_visitante'] ?>" class="team-logo-btn">
        <span class="team-name-btn"><?= $jogo['nome_time_visitante'] ?></span>
    </label>
</div>
```

**Características:**
- **Visual Intuitivo:** Logos dos times e cores diferenciadas
- **Responsivo:** Adapta-se a diferentes tamanhos de tela
- **Estados Visuais:** Feedback visual para seleção

### 5. Funcionalidades JavaScript

#### 5.1 Geração de Palpites Aleatórios

```javascript
function gerarPalpitesAleatorios(button) {
    const jogos = <?= json_encode(array_column($jogos, 'id')) ?>;
    
    jogos.forEach((jogoId, index) => {
        setTimeout(() => {
            const resultados = ['1', '0', '2'];
            const randomIndex = Math.floor(Math.random() * resultados.length);
            const resultado = resultados[randomIndex];
            
            const radio = document.querySelector(`input[name="resultado_${jogoId}"][value="${resultado}"]`);
            const label = document.querySelector(`label[for="${radio.id}"]`);
            
            if (radio && label) {
                radio.checked = true;
                label.classList.add('active');
            }
        }, index * 200);
    });
}
```

**Função:** Permite gerar palpites aleatórios com animação sequencial.

**Correção Implementada:** Adicionado event listener para radio buttons para garantir contagem correta de palpites tanto em seleção manual quanto automática.

#### 5.2 Botão Flutuante de Salvamento

```javascript
function updateFloatingButton() {
    const selectedPalpites = document.querySelectorAll('input[type="radio"]:checked').length;
    const totalJogos = <?= count($jogos) ?>;
    
    const btnText = document.querySelector('#btnSalvarPalpites');
    if (btnText) {
        if (selectedPalpites === 0) {
            btnText.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i>Selecione os Palpites (0/' + totalJogos + ')';
        } else if (selectedPalpites === totalJogos) {
            btnText.innerHTML = '<i class="bi bi-check-circle me-2"></i>Salvar Palpites (' + selectedPalpites + '/' + totalJogos + ')';
        } else {
            btnText.innerHTML = '<i class="bi bi-clock me-2"></i>Palpites (' + selectedPalpites + '/' + totalJogos + ')';
        }
    }
}
```

**Função:** Atualiza dinamicamente o texto do botão mostrando progresso.

#### 5.3 Sistema de Countdown

```javascript
function updateCountdown(element, targetDate) {
    const now = new Date();
    const difference = targetDate - now;
    
    if (difference > 0) {
        const days = Math.floor(difference / (1000 * 60 * 60 * 24));
        const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((difference % (1000 * 60)) / 1000);
        
        // Atualizar display...
    } else {
        // Prazo encerrado - recarregar página
        setTimeout(() => { location.reload(); }, 2000);
    }
}
```

**Função:** Atualiza countdown em tempo real e recarrega página quando prazo expira.

## Fluxos de Uso

### 1. Usuário Não Logado

1. **Acesso:** Visualiza bolão público
2. **Palpites:** Faz palpites (armazenados na sessão)
3. **Submissão:** Redirecionado para login
4. **Login/Cadastro:** Autentica-se no sistema
5. **Retorno:** Volta ao bolão com palpites preservados
6. **Salvamento:** Palpites são salvos no banco

### 2. Usuário Logado com Saldo

1. **Acesso:** Visualiza bolão
2. **Verificação:** Sistema confirma saldo suficiente
3. **Palpites:** Faz palpites diretamente
4. **Salvamento:** Palpites salvos imediatamente

### 3. Usuário Logado sem Saldo

1. **Acesso:** Visualiza bolão
2. **Palpites:** Faz palpites (armazenados temporariamente)
3. **Submissão:** Redirecionado para depósito
4. **Pagamento:** Efetua depósito
5. **Retorno:** Volta ao bolão com palpites preservados
6. **Salvamento:** Palpites são salvos no banco

### 4. Edição de Palpites

1. **Acesso:** Usuário retorna ao bolão
2. **Carregamento:** Palpites existentes são exibidos
3. **Edição:** Modifica palpites desejados
4. **Atualização:** Novos palpites substituem os anteriores

## Segurança e Validações

### 1. Validação de Entrada
- **Sanitização:** IDs convertidos para inteiro
- **Escape:** Strings escapadas para prevenir XSS
- **Validação:** Verificação de existência de parâmetros

### 2. Controle de Acesso
- **Autenticação:** Verificação de login para bolões privados
- **Autorização:** Verificação de saldo para participação
- **Prazo:** Bloqueio após encerramento do prazo

### 3. Integridade de Dados
- **Transações:** Uso de transações para operações críticas
- **Validação:** Verificação de completude dos palpites
- **Tratamento de Erro:** Captura e exibição de erros

## Considerações de Performance

### 1. Otimizações Implementadas
- **Lazy Loading:** Imagens carregadas sob demanda
- **Caching:** Dados do bolão carregados uma vez
- **Compressão:** JSON para armazenamento eficiente

### 2. Pontos de Atenção
- **Consultas:** Múltiplas consultas para verificar participação
- **JSON:** Decodificação de grandes volumes de dados
- **Sessão:** Armazenamento temporário pode crescer

## Manutenção e Extensibilidade

### 1. Pontos de Extensão
- **Modelos de Pagamento:** Facilmente extensível
- **Tipos de Palpite:** Estrutura permite novos formatos
- **Validações:** Sistema modular de validação

### 2. Dependências
- **Bootstrap:** Framework CSS/JS
- **Bootstrap Icons:** Ícones da interface
- **jQuery:** Manipulação DOM (implícito)

### 3. Configurações
- **APP_URL:** URL base da aplicação
- **TEMPLATE_DIR:** Diretório de templates
- **Modelos de Pagamento:** Configurável via função

## Problemas Resolvidos

### 1. Contagem Incorreta de Palpites Selecionados Manualmente

**Problema:** Ao selecionar manualmente os resultados de todos os jogos, o sistema não reconhecia a marcação completa, exibindo no botão de salvar palpites uma contagem incorreta (por exemplo, 10/11). No entanto, ao utilizar a função de gerar palpites automaticamente, o sistema reconhecia corretamente todos os jogos, mostrando 11/11 no botão.

**Causa:** O event listener estava configurado apenas para os cliques nos labels (.btn-palpite), mas não capturava mudanças diretas nos radio buttons. Isso causava inconsistência na contagem quando usuários clicavam diretamente nos inputs radio.

**Solução Implementada:**
```javascript
// Adicionado event listener para radio buttons
document.querySelectorAll('input[type="radio"][name^="resultado_"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Encontrar o label correspondente e adicionar classe active
        const label = document.querySelector(`label[for="${this.id}"]`);
        if (label) {
            // Encontrar o grupo de botões do mesmo jogo
            const btnGroup = label.closest('.palpites-buttons');
            // Remover classe active de todos os botões do grupo
            btnGroup.querySelectorAll('.btn-palpite').forEach(groupBtn => {
                groupBtn.classList.remove('active');
            });
            // Adicionar classe active apenas ao label do radio selecionado
            label.classList.add('active');
        }
        
        // Atualizar botão flutuante
        updateFloatingButton();
    });
});
```

**Resultado:** Agora a contagem de palpites funciona corretamente tanto para seleção manual quanto automática, garantindo que a função `updateFloatingButton()` seja chamada em ambos os cenários.

### 2. Validação de Formulário Desabilitada

**Problema:** O alerta que deveria impedir o envio de palpites incompletos estava sendo "desabilitado" para usuários sem saldo suficiente ou não logados. Isso permitia que usuários tentassem enviar formulários incompletos sem receber o aviso adequado.

**Causa:** A validação JavaScript estava condicionada às verificações PHP de login e saldo, executando apenas se o usuário estivesse logado E pudesse apostar. Para usuários sem saldo, o sistema redirecionava para a página de depósito sem validar se todos os palpites estavam preenchidos.

**Solução Implementada:**
```javascript
// SEMPRE validar se todos os palpites foram preenchidos PRIMEIRO
const radioInputs = this.querySelectorAll('input[type="radio"][name^="resultado_"]');
const jogosIds = new Set();
const palpitesPreenchidos = new Set();

// Coletar todos os IDs de jogos
radioInputs.forEach(input => {
    const jogoId = input.name.replace('resultado_', '');
    jogosIds.add(jogoId);
    if (input.checked) {
        palpitesPreenchidos.add(jogoId);
    }
});

// Verificar se todos os jogos têm palpites
if (palpitesPreenchidos.size !== jogosIds.size) {
    alert('Você precisa dar palpites para todos os jogos antes de enviar.');
    return false;
}

// Após validação dos palpites, verificar outras condições (login, saldo, etc.)
```

**Resultado:** A validação de palpites completos agora é executada SEMPRE, independentemente do status de login ou saldo do usuário, garantindo que o alerta seja exibido corretamente em todos os cenários.

### 3. Modal de Confirmação de Palpites

**Problema:** Não havia confirmação visual antes de salvar os palpites, o que poderia levar a envios acidentais ou sem revisão adequada dos palpites selecionados.

**Solução Implementada:** Adicionado um modal de confirmação que é exibido quando o usuário clica em "Salvar palpites" com todos os palpites preenchidos e estando apto a salvá-los.

**Funcionalidades do Modal:**
- **Mensagem de Confirmação:** "ESTES SÃO SEUS PALPITES. CONFIRMAR?"
- **Indicador de Carregamento:** Após confirmação, exibe um spinner por 3 segundos
- **Botões de Ação:** Cancelar (fecha o modal) e Confirmar (inicia o processo de salvamento)
- **Reset Automático:** Modal retorna ao estado inicial quando fechado

**Estrutura HTML do Modal:**
```html
<div class="modal fade" id="confirmPalpitesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Palpites</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <!-- Conteúdo de confirmação -->
                <div id="confirmContent">
                    <i class="bi bi-question-circle-fill text-warning"></i>
                    <h4>ESTES SÃO SEUS PALPITES. CONFIRMAR?</h4>
                </div>
                <!-- Indicador de carregamento -->
                <div id="loadingContent" style="display: none;">
                    <div class="spinner-border text-primary"></div>
                    <h4>Salvando seus palpites...</h4>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarPalpites">Confirmar</button>
            </div>
        </div>
    </div>
</div>
```

**JavaScript de Controle:**
```javascript
// Modificação no event listener do formulário
<?php else: ?>
    // Mostrar modal de confirmação
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmPalpitesModal'));
    confirmModal.show();
<?php endif; ?>

// Event listener do botão de confirmação
document.getElementById('btnConfirmarPalpites').addEventListener('click', function() {
    // Esconder conteúdo de confirmação
    document.getElementById('confirmContent').style.display = 'none';
    document.getElementById('confirmButtons').style.display = 'none';
    
    // Mostrar indicador de carregamento
    document.getElementById('loadingContent').style.display = 'block';
    
    // Aguardar 3 segundos e então submeter o formulário
    setTimeout(function() {
        document.getElementById('formPalpites').submit();
    }, 3000);
});

// Reset do modal quando fechado
document.getElementById('confirmPalpitesModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('confirmContent').style.display = 'block';
    document.getElementById('confirmButtons').style.display = 'flex';
    document.getElementById('loadingContent').style.display = 'none';
});
```

**Fluxo de Funcionamento:**
1. Usuário preenche todos os palpites
2. Clica no botão "Salvar palpites"
3. Sistema valida se todos os palpites estão preenchidos
4. Se usuário está logado e pode apostar, exibe modal de confirmação
5. Usuário pode cancelar ou confirmar
6. Ao confirmar, mostra indicador de carregamento por 3 segundos
7. Após o tempo, submete o formulário normalmente
8. Modal se reseta automaticamente quando fechado

**Resultado:** Melhora significativa na experiência do usuário, proporcionando uma confirmação visual clara antes do salvamento e feedback durante o processo de envio dos palpites.

## Conclusão

O arquivo `bolao.php` é uma implementação robusta e completa de um sistema de palpites esportivos, oferecendo:

- **Flexibilidade:** Suporte a múltiplos modelos de pagamento
- **Usabilidade:** Interface intuitiva e responsiva
- **Segurança:** Validações e controles de acesso adequados
- **Performance:** Otimizações para carregamento eficiente
- **Manutenibilidade:** Código bem estruturado e documentado
- **Confiabilidade:** Problemas identificados e corrigidos sistematicamente

O sistema atende tanto usuários casuais quanto administradores, proporcionando uma experiência completa de apostas esportivas online.