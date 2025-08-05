<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/classes/ContaManager.php';

// Verifica se está logado
if (!isLoggedIn()) {
    setFlashMessage('warning', 'Você precisa estar logado para acessar sua conta.');
    redirect(APP_URL . '/login.php');
}

$contaManager = new ContaManager();
$jogadorId = getCurrentUserId();

// Busca ou cria conta do jogador
try {
    $conta = $contaManager->buscarContaPorJogador($jogadorId);
    if (!$conta) {
        $contaId = $contaManager->criarConta($jogadorId);
        $conta = $contaManager->buscarContaPorJogador($jogadorId);
    }

    // Calcular saldo atual
    $saldoAtual = $contaManager->getSaldo($conta['id']);

    // Buscar transações com mais detalhes
    $sql = "
        SELECT 
            t.*,
            p.id as palpite_id,
            b.nome as bolao_nome,
            b.id as bolao_id,
            CASE 
                WHEN t.tipo IN ('deposito', 'premio', 'bonus') THEN t.valor 
                WHEN t.tipo IN ('saque', 'aposta') THEN -t.valor 
            END as valor_ajustado,
            CASE
                WHEN t.tipo = 'deposito' AND t.txid IS NOT NULL THEN 'PIX'
                ELSE NULL
            END as metodo_pagamento
        FROM transacoes t
        LEFT JOIN palpites p ON p.id = t.palpite_id
        LEFT JOIN dados_boloes b ON b.id = p.bolao_id
        WHERE t.conta_id = ? 
        ORDER BY COALESCE(t.data_processamento, t.data_solicitacao) DESC 
        LIMIT 20";

    $transacoes = dbFetchAll($sql, [$conta['id']]);

    // Buscar transações pendentes separadamente
    $sqlPendentes = "
        SELECT 
            t.*,
            p.id as palpite_id,
            b.nome as bolao_nome,
            b.id as bolao_id
        FROM transacoes t
        LEFT JOIN palpites p ON p.id = t.palpite_id
        LEFT JOIN dados_boloes b ON b.id = p.bolao_id
        WHERE t.conta_id = ? 
        AND t.status = 'pendente'
        AND t.tipo = 'deposito'
        ORDER BY COALESCE(t.data_processamento, t.data_solicitacao) DESC";

    $transacoesPendentes = dbFetchAll($sqlPendentes, [$conta['id']]);

    // Calcular totais por tipo
    $sql = "
        SELECT 
            SUM(CASE WHEN tipo = 'deposito' AND status = 'aprovado' THEN valor ELSE 0 END) as total_depositos,
            SUM(CASE WHEN tipo = 'saque' AND status = 'aprovado' THEN valor ELSE 0 END) as total_saques,
            SUM(CASE WHEN tipo = 'aposta' THEN valor ELSE 0 END) as total_apostas,
            SUM(CASE WHEN tipo = 'premio' THEN valor ELSE 0 END) as total_premios,
            SUM(CASE WHEN tipo = 'bonus' THEN valor ELSE 0 END) as total_bonus,
            SUM(CASE WHEN tipo = 'deposito' AND status = 'pendente' THEN valor ELSE 0 END) as depositos_pendentes,
            SUM(CASE WHEN tipo = 'saque' AND status = 'pendente' THEN valor ELSE 0 END) as saques_pendentes
        FROM transacoes 
        WHERE conta_id = ?";
    
    $totais = dbFetchOne($sql, [$conta['id']]);

} catch (Exception $e) {
    setFlashMessage('danger', $e->getMessage());
    redirect(APP_URL);
}

// Busca configurações
$sql = "SELECT nome_configuracao, valor FROM configuracoes 
        WHERE nome_configuracao IN ('deposito_minimo', 'deposito_maximo', 'saque_minimo', 'saque_maximo') 
        AND categoria = 'pagamento'";
$configsPagamento = dbFetchAll($sql);

// Define valores padrão
$configs = [
    'deposito_minimo' => 10.00,
    'deposito_maximo' => 5000.00,
    'saque_minimo' => 30.00,
    'saque_maximo' => 5000.00
];

// Atualiza com valores do banco se existirem
foreach ($configsPagamento as $config) {
    if (isset($config['valor']) && isset($config['nome_configuracao'])) {
        $configs[$config['nome_configuracao']] = floatval(trim($config['valor'], '"')); // Remove aspas extras se houver
    }
}

$pageTitle = "Minha Conta";
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Coluna da Esquerda - Saldo e Ações -->
        <div class="col-md-4 mb-4">
            <!-- Saldo com design melhorado -->
            <div class="account-balance">
                <div class="wallet-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <h5 class="mb-2">Saldo Disponível</h5>
                <div class="balance-amount">R$ <?= number_format($saldoAtual, 2, ',', '.') ?></div>
            </div>
            
            <!-- Ações -->
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    
                    <div class="d-grid gap-2">
                        <a href="deposito.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Depositar
                        </a>
                        <?php if ($saldoAtual >= $configs['saque_minimo']): ?>
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#saqueModal">
                                <i class="fas fa-money-bill-wave"></i> Solicitar Saque
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-primary" disabled title="Saldo mínimo para saque: R$ <?= number_format($configs['saque_minimo'], 2, ',', '.') ?>">
                                <i class="fas fa-money-bill-wave"></i> Solicitar Saque
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Transações Pendentes -->
                    <?php if (!empty($transacoesPendentes)): ?>
                    <div class="mt-4">
                        <h6 class="text-warning mb-3">
                            <i class="fas fa-clock"></i> Depósitos Pendentes
                        </h6>
                        <div class="list-group list-group-flush">
                            <?php foreach ($transacoesPendentes as $pendente): ?>
                            <div class="pending-transaction">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted">
                                        <?php 
                                        $dataPendente = $pendente['data_processamento'] ?: $pendente['data_solicitacao'];
                                        echo formatDateTime($dataPendente);
                                        ?>
                                    </small>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-clock"></i> Pendente
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-success">
                                        R$ <?= number_format($pendente['valor'], 2, ',', '.') ?>
                                    </span>
                                    <a href="deposito.php?id=<?= $pendente['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-redo-alt"></i> Retomar Pagamento
                                    </a>
                                </div>
                                <?php if ($pendente['txid']): ?>
                                <small class="text-muted d-block">
                                    <i class="fas fa-fingerprint"></i> PIX ID: <?= substr($pendente['txid'], 0, 10) ?>...
                                </small>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Resumo Financeiro -->
                    <div class="mt-4">
                        <h6 class="text-muted mb-3">Resumo Financeiro</h6>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-arrow-circle-down text-success"></i> Total Depositado</span>
                                <div class="text-end">
                                    <span class="text-success">R$ <?= number_format($totais['total_depositos'], 2, ',', '.') ?></span>
                                    <?php if ($totais['depositos_pendentes'] > 0): ?>
                                        <br>
                                        <small class="text-warning">
                                            <i class="fas fa-clock"></i>
                                            Pendente: R$ <?= number_format($totais['depositos_pendentes'], 2, ',', '.') ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-arrow-circle-up text-danger"></i> Total Sacado</span>
                                <div class="text-end">
                                    <span class="text-danger">R$ <?= number_format($totais['total_saques'], 2, ',', '.') ?></span>
                                    <?php if ($totais['saques_pendentes'] > 0): ?>
                                        <br>
                                        <small class="text-warning">
                                            <i class="fas fa-clock"></i>
                                            Pendente: R$ <?= number_format($totais['saques_pendentes'], 2, ',', '.') ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-ticket-alt text-warning"></i> Total em Apostas</span>
                                <span class="text-warning">R$ <?= number_format($totais['total_apostas'], 2, ',', '.') ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-trophy text-primary"></i> Total em Prêmios</span>
                                <span class="text-primary">R$ <?= number_format($totais['total_premios'], 2, ',', '.') ?></span>
                            </div>
                            <?php if ($totais['total_bonus'] > 0): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-gift text-info"></i> Total em Bônus</span>
                                <span class="text-info">R$ <?= number_format($totais['total_bonus'], 2, ',', '.') ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Coluna da Direita - Histórico -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Histórico de Transações</h5>
                        <a href="historico-transacoes.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-history"></i> Ver Histórico Completo
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($transacoes)): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-receipt fa-3x text-muted"></i>
                            </div>
                            <h5 class="text-muted">Nenhuma transação encontrada</h5>
                            <p class="text-muted">Suas transações aparecerão aqui quando você fizer depósitos ou apostas.</p>
                            <a href="deposito.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Fazer Primeiro Depósito
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Detalhes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transacoes as $transacao): ?>
                                        <tr class="transaction-item <?= $transacao['tipo'] ?>">
                                            <td>
                                                <?php 
                                                $dataExibir = $transacao['data_processamento'] ?: $transacao['data_solicitacao'];
                                                ?>
                                                <div><?= formatDate($dataExibir) ?></div>
                                                <small class="text-muted"><?= formatTime($dataExibir) ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                    $tipoClass = '';
                                                    switch ($transacao['tipo']) {
                                                        case 'deposito':
                                                            $tipoClass = 'success';
                                                            $tipoIcon = 'plus-circle';
                                                            break;
                                                        case 'saque':
                                                            $tipoClass = 'danger';
                                                            $tipoIcon = 'minus-circle';
                                                            break;
                                                        case 'aposta':
                                                            $tipoClass = 'warning';
                                                            $tipoIcon = 'ticket-alt';
                                                            break;
                                                        case 'premio':
                                                            $tipoClass = 'primary';
                                                            $tipoIcon = 'trophy';
                                                            break;
                                                        case 'bonus':
                                                            $tipoClass = 'info';
                                                            $tipoIcon = 'gift';
                                                            break;
                                                    }
                                                ?>
                                                <span class="text-<?= $tipoClass ?>">
                                                    <i class="fas fa-<?= $tipoIcon ?> me-1"></i>
                                                    <?= ucfirst($transacao['tipo']) ?>
                                                </span>
                                                <?php if ($transacao['metodo_pagamento']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                        <?= $transacao['metodo_pagamento'] ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="text-<?= $transacao['valor_ajustado'] > 0 ? 'success' : 'danger' ?>">
                                                    <?= $transacao['valor_ajustado'] > 0 ? '+' : '' ?>
                                                    R$ <?= number_format(abs($transacao['valor_ajustado']), 2, ',', '.') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                    $statusClass = '';
                                                    $statusIcon = '';
                                                    switch ($transacao['status']) {
                                                        case 'pendente':
                                                            $statusClass = 'warning';
                                                            $statusIcon = 'clock';
                                                            break;
                                                        case 'aprovado':
                                                            $statusClass = 'success';
                                                            $statusIcon = 'check-circle';
                                                            break;
                                                        case 'rejeitado':
                                                            $statusClass = 'danger';
                                                            $statusIcon = 'times-circle';
                                                            break;
                                                        case 'cancelado':
                                                            $statusClass = 'secondary';
                                                            $statusIcon = 'ban';
                                                            break;
                                                        case 'processando':
                                                            $statusClass = 'info';
                                                            $statusIcon = 'spinner';
                                                            break;
                                                    }
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <i class="fas fa-<?= $statusIcon ?> me-1"></i>
                                                    <?= ucfirst($transacao['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($transacao['palpite_id']): ?>
                                                    <a href="palpite.php?id=<?= $transacao['palpite_id'] ?>" class="text-decoration-none">
                                                        <small class="d-block">
                                                            <i class="fas fa-ticket-alt text-warning me-1"></i>
                                                            Palpite #<?= $transacao['palpite_id'] ?>
                                                        </small>
                                                        <?php if ($transacao['bolao_nome']): ?>
                                                            <small class="text-muted d-block">
                                                                <i class="fas fa-trophy text-primary me-1"></i>
                                                                <?= $transacao['bolao_nome'] ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php elseif ($transacao['txid']): ?>
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-fingerprint me-1"></i>
                                                        PIX ID: <?= substr($transacao['txid'], 0, 10) ?>...
                                                    </small>
                                                    <?php if ($transacao['status'] === 'pendente'): ?>
                                                        <a href="deposito.php?id=<?= $transacao['id'] ?>" class="btn btn-sm btn-outline-primary mt-1">
                                                            <i class="fas fa-sync-alt"></i> Verificar
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?> 