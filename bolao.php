<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
// database_functions.php não é mais necessário pois está incluído em database.php
require_once __DIR__ . '/includes/functions.php';

// Obter o ID ou slug do bolão da URL
$bolaoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$bolaoSlug = isset($_GET['slug']) ? $_GET['slug'] : '';

// Se não tiver ID nem slug, redireciona para a lista de bolões públicos
if ($bolaoId <= 0 && empty($bolaoSlug)) {
    redirect(APP_URL . '/boloes.php');
}

// Buscar dados do bolão (pelo ID ou slug)
if ($bolaoId > 0) {
    $bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ? AND status = 1", [$bolaoId]);
} else {
    $bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE slug = ? AND status = 1", [$bolaoSlug]);
}

// DEBUG: Mostrar ID do bolão
// echo "ID do Bolão: " . ($bolao['id'] ?? 'não encontrado') . "<br>";

// Se bolão não existe ou não está ativo
if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado ou não está ativo.');
    redirect(APP_URL . '/boloes.php');
}

// Se bolão não é público e usuário não está logado, redirecionar
if ($bolao['publico'] != 1 && !isLoggedIn()) {
    setFlashMessage('warning', 'Este bolão é privado. Faça login para acessá-lo.');
    redirect(APP_URL . '/login.php');
}

// Verificar status do usuário logado
$usuarioId = isLoggedIn() ? getCurrentUserId() : 0;
$podeApostar = false;
$saldoInfo = null;

if ($usuarioId) {
    // Verificar modelo de pagamento
    $modeloPagamento = getModeloPagamento();
    
    if ($modeloPagamento === 'conta_saldo') {
        // Verificar saldo
        $saldoInfo = verificarSaldoJogador($usuarioId);
        $podeApostar = $saldoInfo['tem_saldo'] && $saldoInfo['saldo_atual'] >= $bolao['valor_participacao'];
    } else {
        // No modelo por_aposta, sempre pode apostar (pagará depois)
        $podeApostar = true;
    }
}

// Decodificar dados JSON
$jogos = json_decode($bolao['jogos'], true) ?: [];
$campeonatos = json_decode($bolao['campeonatos'], true) ?: [];

// Os logos agora são carregados diretamente do JSON do bolão
// Não é mais necessário buscar os logos da API Football

// Verificar se o usuário já tem palpites salvos
$palpites = [];

// Ordenar jogos por data
usort($jogos, function($a, $b) {
    $dateA = isset($a['data_iso']) ? $a['data_iso'] : $a['data'];
    $dateB = isset($b['data_iso']) ? $b['data_iso'] : $b['data'];
    return strtotime($dateA) - strtotime($dateB);
});

// Calcular prazo limite automaticamente: 5 minutos antes do primeiro jogo
$prazoEncerrado = false;
$dataLimite = calcularPrazoLimitePalpites($jogos, $bolao['data_limite_palpitar']);

if ($dataLimite) {
    $agora = new DateTime();
    $prazoEncerrado = $agora > $dataLimite;
}

// Inicializar variáveis de palpites
$palpites = [];
$palpitesSessao = [];

// Se o usuário estiver logado, verificar palpites salvos no banco
if ($usuarioId) {
    // Verificar se já existe um registro do usuário para este bolão
    $participante = dbFetchOne("SELECT id FROM participacoes WHERE bolao_id = ? AND jogador_id = ?", [$bolao['id'], $usuarioId]);
    
    // Buscar palpites do usuário se ele já participou
    if ($participante) {
        $palpitesUsuario = dbFetchOne("SELECT palpites FROM palpites WHERE bolao_id = ? AND jogador_id = ?", [$bolao['id'], $usuarioId]);
        if ($palpitesUsuario) {
            $palpites = json_decode($palpitesUsuario['palpites'], true) ?: [];
        }
    }
} 
// Verificar se há palpites salvos na sessão
else if (isset($_SESSION['palpites_temp'][$bolao['id']])) {
    $palpitesSessao = $_SESSION['palpites_temp'][$bolao['id']];
}

// Processar envio de palpites
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se o prazo está encerrado
    if ($prazoEncerrado) {
        $mensagem = [
            'tipo' => 'danger',
            'texto' => 'O prazo para envio de palpites já foi encerrado.'
        ];
    } else {
        // Coletar palpites do formulário
        $palpitesForm = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'resultado_') === 0) {
                $jogoId = substr($key, strlen('resultado_'));
                $palpitesForm[$jogoId] = $value; // "1" = casa vence, "0" = empate, "2" = visitante vence
            }
        }

        // Verificar se todos os jogos foram palpitados
        if (count($palpitesForm) !== count($jogos)) {
            $mensagem = [
                'tipo' => 'warning',
                'texto' => 'Você precisa dar palpites para todos os jogos.'
            ];
        } else {
            // Se não estiver logado, redirecionar para login
            if (!isLoggedIn()) {
                $_SESSION['palpites_temp'] = [
                    'bolao_id' => $bolao['id'],
                    'palpites' => $palpitesForm
                ];
                $_SESSION['login_redirect'] = APP_URL . '/bolao.php?slug=' . $bolao['slug'];
                setFlashMessage('info', 'Por favor, faça login para salvar seus palpites.');
                redirect(APP_URL . '/login.php');
            } 
            // Se estiver logado mas não pagou, redirecionar para pagamento
            elseif (!$podeApostar) {
                $_SESSION['palpites_temp'] = [
                    'bolao_id' => $bolao['id'],
                    'palpites' => $palpitesForm
                ];
                setFlashMessage('warning', 'Você precisa efetuar o pagamento para participar do bolão.');
                redirect(APP_URL . '/pagamento.php');
            }
            // Se estiver tudo ok, salvar no banco
            else {
                try {
                    // Verifica se o usuário já é participante do bolão
                    $participante = dbFetchOne(
                        "SELECT id FROM participacoes WHERE bolao_id = ? AND jogador_id = ?", 
                        [$bolao['id'], $usuarioId]
                    );
                    
                    // Se não for participante, criar registro
                    if (!$participante) {
                        dbInsert('participacoes', [
                            'bolao_id' => $bolao['id'],
                            'jogador_id' => $usuarioId,
                            'data_entrada' => date('Y-m-d H:i:s'),
                            'status' => 1
                        ]);
                    }
                    
                    // Verifica se já tem palpites
                    $palpiteExistente = dbFetchOne(
                        "SELECT id FROM palpites WHERE bolao_id = ? AND jogador_id = ?", 
                        [$bolao['id'], $usuarioId]
                    );
                    
                    // Preparar dados para salvar
                    $palpitesData = [
                        'bolao_id' => $bolao['id'],
                        'jogador_id' => $usuarioId,
                        'palpites' => json_encode($palpitesForm),
                        'data_palpite' => date('Y-m-d H:i:s')
                    ];
                    
                    if ($palpiteExistente) {
                        // Atualiza palpites existentes
                        dbUpdate('palpites', $palpitesData, 'id = ?', [$palpiteExistente['id']]);
                        $mensagem = [
                            'tipo' => 'success',
                            'texto' => 'Seus palpites foram atualizados com sucesso!'
                        ];
                    } else {
                        // Insere novos palpites
                        dbInsert('palpites', $palpitesData);
                        $mensagem = [
                            'tipo' => 'success',
                            'texto' => 'Seus palpites foram registrados com sucesso!'
                        ];
                    }
                    
                    // Limpar palpites temporários da sessão
                    if (isset($_SESSION['palpites_temp'])) {
                        unset($_SESSION['palpites_temp']);
                    }
                    
                } catch (Exception $e) {
                    $mensagem = [
                        'tipo' => 'danger',
                        'texto' => 'Erro ao salvar os palpites: ' . $e->getMessage()
                    ];
                }
            }
        }
    }
}

// Definir quais palpites exibir (prioridade para palpites salvos no banco)
$palpitesExibir = !empty($palpites) ? $palpites : $palpitesSessao;

// Título da página
$pageTitle = $bolao['nome'];
include TEMPLATE_DIR . '/header.php';
?>

<div class="row">
    <!-- Informações do Bolão -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-2" style="font-size: 1.2rem;"></i>
                    <h5 class="card-title mb-0 fw-bold">Informações do Bolão</h5>
                </div>
            </div>
            <div class="card-body p-4">
                <!-- Título do Bolão -->
                <div class="text-center mb-4">
                    <h3 class="fw-bold text-primary mb-2"><?= htmlspecialchars($bolao['nome']) ?></h3>
                    <?php if (!empty($bolao['descricao'])): ?>
                        <p class="text-muted small"><?= nl2br(htmlspecialchars($bolao['descricao'])) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Informações em Cards Pequenos -->
                <div class="row g-2 mb-3">
                    <!-- Período -->
                    <div class="col-12">
                        <div class="info-card p-3 rounded-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                            <div class="d-flex align-items-center">
                                <div class="info-icon me-3">
                                    <i class="bi bi-calendar-range" style="font-size: 1.5rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="info-label fw-semibold">Período</div>
                                    <div class="info-value"><?= formatDate($bolao['data_inicio']) ?> a <?= formatDate($bolao['data_fim']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Prazo para Palpites -->
                    <?php if ($dataLimite): ?>
                    <div class="col-12">
                        <div class="info-card p-3 rounded-3 <?= $prazoEncerrado ? 'bg-light border border-warning' : '' ?>" style="<?= !$prazoEncerrado ? 'background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;' : 'color: #856404;' ?>">
                            <div class="d-flex align-items-center">
                                <div class="info-icon me-3">
                                    <i class="bi bi-<?= $prazoEncerrado ? 'clock-history' : 'alarm' ?>" style="font-size: 1.5rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="info-label fw-semibold"><?= $prazoEncerrado ? 'Prazo Encerrado' : 'Prazo para Palpites' ?></div>
                                    <div class="info-value">
                                        <?= $dataLimite->format('d/m/Y H:i') ?>
                                        <small class="d-block opacity-75">(5 min antes do 1º jogo)</small>
                                        <?php if ($prazoEncerrado): ?>
                                            <span class="badge bg-danger mt-1">Encerrado</span>
                                        <?php else: ?>
                                            <div class="countdown-timer mt-2" data-target="<?= $dataLimite->format('Y-m-d H:i:s') ?>">
                                                <div class="countdown-display">
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-clock me-1"></i>
                                                        <span class="countdown-text">Carregando...</span>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                
                <!-- Prêmios -->
                <?php if ($bolao['premio_total'] > 0 || $bolao['premio_rodada'] > 0): ?>
                <div class="mb-3">
                    <h6 class="fw-bold text-success mb-2">
                        <i class="bi bi-trophy-fill me-1"></i>
                        Prêmios
                    </h6>
                    <div class="row g-2">
                        <?php if ($bolao['premio_total'] > 0): ?>
                        <div class="col-6">
                            <div class="prize-card text-center p-3 rounded-3" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                                <i class="bi bi-trophy text-warning" style="font-size: 1.8rem;"></i>
                                <div class="fw-bold text-dark mt-1">Prêmio Total</div>
                                <div class="h5 fw-bold text-success mb-0"><?= formatMoney($bolao['premio_total']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($bolao['premio_rodada'] > 0): ?>
                        <div class="col-6">
                            <div class="prize-card text-center p-3 rounded-3" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                                <i class="bi bi-award text-info" style="font-size: 1.8rem;"></i>
                                <div class="fw-bold text-dark mt-1">Por Rodada</div>
                                <div class="h5 fw-bold text-info mb-0"><?= formatMoney($bolao['premio_rodada']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Informações Financeiras -->
                <div class="row g-2 mb-3">
                    <?php if ($bolao['valor_participacao'] > 0): ?>
                    <div class="col-12">
                        <div class="info-card p-3 rounded-3" style="background: linear-gradient(135deg, #a8e6cf 0%, #dcedc8 100%);">
                            <div class="d-flex align-items-center">
                                <div class="info-icon me-3">
                                    <i class="bi bi-currency-dollar text-success" style="font-size: 1.5rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="info-label fw-semibold text-success">Valor de Participação</div>
                                    <div class="info-value text-success h6 mb-0"><?= formatMoney($bolao['valor_participacao']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn() && getModeloPagamento() === 'conta_saldo' && $saldoInfo): ?>
                    <div class="col-12">
                        <div class="info-card p-3 rounded-3" style="background: <?= $podeApostar ? 'linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%)' : 'linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%)' ?>;">
                            <div class="d-flex align-items-center">
                                <div class="info-icon me-3">
                                    <i class="bi bi-wallet2 <?= $podeApostar ? 'text-success' : 'text-danger' ?>" style="font-size: 1.5rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="info-label fw-semibold <?= $podeApostar ? 'text-success' : 'text-danger' ?>">Seu Saldo</div>
                                    <div class="info-value <?= $podeApostar ? 'text-success' : 'text-danger' ?> h6 mb-0">
                                        <?= formatMoney($saldoInfo['saldo_atual']) ?>
                                        <?php if (!$podeApostar): ?>
                                            <span class="badge bg-danger ms-2">Insuficiente</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Informações Adicionais -->
                <div class="row g-2">
                    <div class="col-6">
                        <div class="stat-card text-center p-2 rounded-3 bg-light">
                            <i class="bi bi-joystick text-primary" style="font-size: 1.2rem;"></i>
                            <div class="small fw-semibold text-muted">Total de Jogos</div>
                            <div class="h6 fw-bold text-primary mb-0"><?= count($jogos) ?></div>
                        </div>
                    </div>
                    
                    <?php if ($bolao['max_participantes'] > 0): ?>
                    <div class="col-6">
                        <div class="stat-card text-center p-2 rounded-3 bg-light">
                            <i class="bi bi-people text-info" style="font-size: 1.2rem;"></i>
                            <div class="small fw-semibold text-muted">Máx. Participantes</div>
                            <div class="h6 fw-bold text-info mb-0"><?= $bolao['max_participantes'] ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Aviso de Saldo Insuficiente -->
                <?php if (isLoggedIn() && !$podeApostar && getModeloPagamento() === 'conta_saldo'): ?>
                <div class="alert alert-warning border-0 rounded-3 mt-3" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-3" style="font-size: 1.5rem;"></i>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-warning">Saldo Insuficiente</div>
                            <small class="text-muted">Você precisa depositar mais para participar deste bolão.</small>
                            <div class="mt-2">
                                <a href="<?= APP_URL ?>/minha-conta.php" class="btn btn-warning btn-sm">
                                    <i class="bi bi-plus-circle me-1"></i>
                                    Depositar Agora
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lista de Jogos -->
    <div class="col-md-8">
        <!-- Loader -->
        <div id="palpitesLoader" class="text-center p-5">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-3">Carregando palpites...</p>
        </div>

        <!-- Área de Palpites (inicialmente oculta) -->
        <div id="areaPalpites" style="display: none;">
        <?php if (!empty($jogos)): ?>
            <?php
            // Verificar se o usuário já tem palpites para este bolão
            /* if ($usuarioId) {
                $palpiteExistente = dbFetchOne(
                    "SELECT COUNT(*) as total FROM palpites WHERE bolao_id = ? AND jogador_id = ?",
                    [$bolao['id'], $usuarioId]
                );
                
                if ($palpiteExistente && $palpiteExistente['total'] > 0) {
                    echo '<div class="alert alert-warning mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Você já fez palpites para este bolão. Novos palpites irão substituir os anteriores.
                    </div>';
                }
            } */
            ?>
            
            <form method="post" action="<?= APP_URL ?>/salvar-palpite.php" id="formPalpites">
                <input type="hidden" name="bolao_id" value="<?= $bolao['id'] ?>">
                <input type="hidden" name="bolao_slug" value="<?= $bolao['slug'] ?>">
                
                <!-- Aviso de prazo encerrado -->
                <?php if ($prazoEncerrado): ?>
                    <div class="card mb-4 border-warning">
                        <div class="card-body">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-clock-history text-warning me-3" style="font-size: 2rem; margin-top: 0.25rem;"></i>
                                <div class="flex-grow-1">
                                    <h5 class="card-title text-warning mb-2">
                                        <strong>⏰ Oops! Você perdeu o prazo</strong>
                                    </h5>
                                    <p class="card-text mb-2">
                                        Os palpites foram encerrados em <strong><?= $dataLimite->format('d/m/Y') ?> às <?= $dataLimite->format('H:i') ?></strong>, 
                                        que foi exatamente 5 minutos antes do primeiro jogo começar.
                                    </p>
                                    <p class="card-text text-muted mb-0">
                                        <small>
                                            <i class="bi bi-eye me-1"></i>
                                            Mas não se preocupe! Você ainda pode acompanhar todos os jogos e torcer pelos resultados aqui mesmo.
                                        </small>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Card de palpites aleatórios -->
                    <div class="card mb-4 border-primary">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="bi bi-dice-5-fill text-primary me-2"></i>
                                    Quer ajuda com os palpites?
                                </h5>
                                <p class="card-text text-muted mb-0">
                                    Clique no botão ao lado para gerar palpites aleatórios
                                </p>
                            </div>
                            <button type="button" 
                                    class="btn btn-primary btn-lg" 
                                    onclick="gerarPalpitesAleatorios(this)"
                                    data-bs-toggle="tooltip" 
                                    data-bs-placement="top" 
                                    title="Gera resultados aleatórios para todos os jogos">
                                <i class="bi bi-shuffle me-2"></i>
                                Gerar Palpites
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($jogos as $jogo): ?>
                    <?php 
                    $jogoId = $jogo['id'];
                    $palpiteJogo = $palpites[$jogoId] ?? $palpitesSessao[$jogoId] ?? null;
                    $disabled = $prazoEncerrado ? 'disabled' : '';
                    ?>
                    <div class="card mb-3 <?= $prazoEncerrado ? 'border-secondary' : '' ?>">
                        <div class="card-body <?= $prazoEncerrado ? 'bg-light' : '' ?>">
                            <?php if ($prazoEncerrado): ?>
                                <div class="position-relative">
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <i class="bi bi-lock-fill text-muted" title="Prazo encerrado - não é mais possível palpitar neste jogo"></i>
                                    </div>
                            <?php endif; ?>
                            <div class="row align-items-center">
                                <div class="col-md-12">
                                    <div class="palpites-container text-center">
                                        <div class="mb-3 game-datetime">
                                            <span class="date-badge">
                                                <i class="bi bi-calendar-event me-1"></i>
                                                <?php 
                                                // Se temos data_formatada (formato brasileiro), usar diretamente a parte da data
                                                if (!empty($jogo['data_formatada'])) {
                                                    echo substr($jogo['data_formatada'], 0, 10); // Pega apenas "dd/mm/yyyy"
                                                } else {
                                                    // Se só temos 'data' (formato ISO), converter corretamente
                                                    echo date('d/m/Y', strtotime($jogo['data']));
                                                }
                                                ?>
                                            </span>
                                            <span class="time-badge ms-2">
                                                <i class="bi bi-clock me-1"></i>
                                                <?php 
                                                // Se temos data_formatada (formato brasileiro), usar diretamente a parte do horário
                                                if (!empty($jogo['data_formatada'])) {
                                                    echo substr($jogo['data_formatada'], 11, 5); // Pega apenas "HH:mm"
                                                } else {
                                                    // Se só temos 'data' (formato ISO), converter corretamente
                                                    echo date('H:i', strtotime($jogo['data']));
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <div class="palpites-buttons btn-group" role="group">
                                                <input type="radio" 
                                                       class="btn-check" 
                                                       name="resultado_<?= $jogo['id'] ?>" 
                                                       id="casa_<?= $jogo['id'] ?>" 
                                                       value="1" 
                                                       <?= ($palpiteJogo === '1') ? 'checked' : '' ?> 
                                                       <?= $disabled ?>>
                                                <label class="btn btn-outline-success btn-palpite btn-time <?= ($palpiteJogo === '1') ? 'active' : '' ?>" 
                                                       for="casa_<?= $jogo['id'] ?>">
                                                    <img src="<?= $jogo['logo_time_casa'] ?>" alt="<?= $jogo['nome_time_casa'] ?>" class="team-logo-btn">
                                                    <span class="team-name-btn"><?= $jogo['nome_time_casa'] ?></span>
                                                </label>

                                                <input type="radio" 
                                                       class="btn-check" 
                                                       name="resultado_<?= $jogo['id'] ?>" 
                                                       id="empate_<?= $jogo['id'] ?>" 
                                                       value="0" 
                                                       <?= ($palpiteJogo === '0') ? 'checked' : '' ?> 
                                                       <?= $disabled ?>>
                                                <label class="btn btn-outline-warning btn-palpite <?= ($palpiteJogo === '0') ? 'active' : '' ?>" 
                                                       for="empate_<?= $jogo['id'] ?>">
                                                    <i class="bi bi-x-lg"></i>
                                                </label>

                                                <input type="radio" 
                                                       class="btn-check" 
                                                       name="resultado_<?= $jogo['id'] ?>" 
                                                       id="fora_<?= $jogo['id'] ?>" 
                                                       value="2" 
                                                       <?= ($palpiteJogo === '2') ? 'checked' : '' ?> 
                                                       <?= $disabled ?>>
                                                <label class="btn btn-outline-danger btn-palpite btn-time <?= ($palpiteJogo === '2') ? 'active' : '' ?>" 
                                                       for="fora_<?= $jogo['id'] ?>">
                                                    <img src="<?= $jogo['logo_time_visitante'] ?>" alt="<?= $jogo['nome_time_visitante'] ?>" class="team-logo-btn">
                                                    <span class="team-name-btn"><?= $jogo['nome_time_visitante'] ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php if ($prazoEncerrado): ?>
                                </div> <!-- Fecha position-relative -->
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <a href="<?= APP_URL ?>/boloes.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar para Bolões
                    </a>
                    <?php if (!$prazoEncerrado): ?>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle"></i> Salvar Palpites
                        </button>
                    <?php else: ?>
                        <div class="text-muted d-flex align-items-center">
                            <i class="bi bi-hourglass-bottom me-2"></i>
                            <div>
                                <small class="fw-bold">Tempo esgotado!</small>
                                <br>
                                <small>Os palpites já foram encerrados</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-info">
                Nenhum jogo cadastrado neste bolão ainda.
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isset($mensagem) && !empty($mensagem)): ?>
    <div class="alert alert-<?= $mensagem['tipo'] ?> mt-3">
        <?= $mensagem['texto'] ?>
    </div>
<?php endif; ?>

<!-- Modal de Login/Cadastro -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginModalLabel">Acesso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 border-end">
                        <h4>Já tenho cadastro</h4>
                        <form id="loginForm" class="mt-3">
                            <div class="mb-3">
                                <label for="loginEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="loginEmail" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="loginPassword" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="loginPassword" name="senha" required>
                            </div>
                            <div class="alert alert-danger d-none" id="loginError"></div>
                            <button type="submit" class="btn btn-primary">Entrar</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <h4>Quero me cadastrar</h4>
                        <form id="registerForm" class="mt-3">
                            <div class="mb-3">
                                <label for="registerName" class="form-label">Nome</label>
                                <input type="text" class="form-control" id="registerName" name="nome" required>
                            </div>
                            <div class="mb-3">
                                <label for="registerEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="registerEmail" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="registerPassword" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="registerPassword" name="senha" required>
                            </div>
                            <div class="alert alert-danger d-none" id="registerError"></div>
                            <button type="submit" class="btn btn-success">Cadastrar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Função para mostrar o loader e esconder a área de palpites
function showLoader() {
    document.getElementById('palpitesLoader').style.display = 'block';
    document.getElementById('areaPalpites').style.display = 'none';
}

// Função para esconder o loader e mostrar a área de palpites
function hideLoader() {
    document.getElementById('palpitesLoader').style.display = 'none';
    document.getElementById('areaPalpites').style.display = 'block';
}

// Quando a página carregar completamente
document.addEventListener('DOMContentLoaded', function() {
    // Mostra o loader inicialmente
    showLoader();
    
    // Espera todas as imagens carregarem
    Promise.all(Array.from(document.images).map(img => {
        if (img.complete) {
            return Promise.resolve();
        } else {
            return new Promise(resolve => {
                img.addEventListener('load', resolve);
                img.addEventListener('error', resolve); // Em caso de erro, também continua
            });
        }
    })).then(() => {
        // Quando todas as imagens estiverem carregadas, esconde o loader
        hideLoader();
    });
});

// Inicializar tooltips do Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle form submission
    document.getElementById('formPalpites').addEventListener('submit', function(e) {
        e.preventDefault();
        
        <?php if (!isLoggedIn()): ?>
            const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            loginModal.show();
        <?php elseif (!$podeApostar && getModeloPagamento() === 'conta_saldo'): ?>
            window.location.href = '<?= APP_URL ?>/minha-conta.php';
        <?php else: ?>
            this.submit();
        <?php endif; ?>
    });

    // Handle login form submission
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('<?= APP_URL ?>/ajax/login.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Submit the palpites form after successful login
                document.getElementById('formPalpites').submit();
            } else {
                document.getElementById('loginError').textContent = data.message;
                document.getElementById('loginError').classList.remove('d-none');
            }
        });
    });

    // Handle register form submission
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('<?= APP_URL ?>/ajax/register.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Submit the palpites form after successful registration
                document.getElementById('formPalpites').submit();
            } else {
                document.getElementById('registerError').textContent = data.message;
                document.getElementById('registerError').classList.remove('d-none');
            }
        });
    });

    // Adicionar evento de clique para os botões de palpite
    document.querySelectorAll('.btn-palpite').forEach(btn => {
        btn.addEventListener('click', function() {
            // Encontrar o grupo de botões do mesmo jogo
            const btnGroup = this.closest('.palpites-buttons');
            // Remover classe active de todos os botões do grupo
            btnGroup.querySelectorAll('.btn-palpite').forEach(groupBtn => {
                groupBtn.classList.remove('active');
            });
            // Adicionar classe active apenas ao botão clicado
            this.classList.add('active');
        });
    });
});

function gerarPalpitesAleatorios(button) {
    // Desabilitar o botão e mostrar loading
    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i>Gerando...';
    button.classList.add('btn-generating');
    
    const jogos = <?= json_encode(array_column($jogos, 'id')) ?>;
    
    // Limpar todas as seleções anteriores primeiro
    document.querySelectorAll('.btn-palpite').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.checked = false;
    });
    
    // Pequeno delay para efeito visual
    setTimeout(() => {
        jogos.forEach((jogoId, index) => {
            setTimeout(() => {
                const resultados = ['1', '0', '2'];
                const randomIndex = Math.floor(Math.random() * resultados.length);
                const resultado = resultados[randomIndex];
                
                // Encontrar o radio button e sua label
                const radio = document.querySelector(`input[name="resultado_${jogoId}"][value="${resultado}"]`);
                const label = document.querySelector(`label[for="${radio.id}"]`);
                
                if (radio && label) {
                    // Marcar o radio e adicionar classe ativa
                    radio.checked = true;
                    label.classList.add('active');
                    
                    // Adicionar animação de seleção
                    label.classList.add('selecting');
                    setTimeout(() => {
                        label.classList.remove('selecting');
                    }, 500);
                }
            }, index * 200); // Delay maior entre cada seleção
        });
        
        // Restaurar o botão após todos os palpites
        setTimeout(() => {
            button.disabled = false;
            button.innerHTML = originalText;
            button.classList.remove('btn-generating');
            
            // Mostrar toast de sucesso
            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.innerHTML = `
                <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Palpites gerados com sucesso!
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            document.body.appendChild(toastContainer);
            const toastElement = toastContainer.querySelector('.toast');
            const bsToast = new bootstrap.Toast(toastElement);
            bsToast.show();
            
            // Remover o toast após ser fechado
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastContainer.remove();
            });
        }, jogos.length * 200 + 500);
    }, 500);
}
</script>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes fadeIn {
    from { opacity: 0.5; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.btn-generating {
    position: relative;
    overflow: hidden;
}

.selecting {
    animation: fadeIn 0.5s ease-out;
}

.palpites-buttons {
    display: flex;
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
    flex-direction: row; /* Força layout horizontal */
    flex-wrap: nowrap; /* Impede quebra de linha */
}

.palpites-buttons .btn-palpite {
    flex: 1 1 0; /* Distribuição igual do espaço */
    min-width: 0; /* Permite que os botões encolham */
    height: 100px;
    transition: all 0.3s ease;
    border-radius: 0;
    margin: 0;
    padding: 3px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    border: 2px solid #6c757d;
    opacity: 0.9;
    color: #495057;
    background-color: rgba(108, 117, 125, 0.1);
}

/* Botão da esquerda */
.palpites-buttons .btn-palpite:first-child {
    border-radius: 8px 0 0 8px;
    border-right: 1px solid #6c757d;
}

/* Botão do meio */
.palpites-buttons .btn-palpite:not(:first-child):not(:last-child) {
    border-radius: 0;
    border-left: 1px solid #6c757d;
    border-right: 1px solid #6c757d;
}

/* Botão da direita */
.palpites-buttons .btn-palpite:last-child {
    border-radius: 0 8px 8px 0;
    border-left: 1px solid #6c757d;
}

/* Ajustes para telas pequenas */
@media (max-width: 576px) {
    .palpites-buttons {
        min-width: 100%; /* Força largura total */
    }

    .palpites-buttons .btn-palpite {
        padding: 2px;
    }

    .team-logo-btn {
        width: 35px; /* Logos um pouco menores no mobile */
        height: 35px;
    }

    .team-name-btn {
        font-size: 9px; /* Fonte um pouco menor no mobile */
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btn-palpite.btn-outline-warning i {
        font-size: 24px; /* X um pouco menor no mobile */
    }
}

/* Mantém os outros estilos... */
.team-logo-btn {
    width: 45px;
    height: 45px;
    object-fit: contain;
    margin-bottom: 2px;
}

.team-name-btn {
    font-size: 10px;
    text-align: center;
    line-height: 1.1;
    font-weight: bold;
    margin-top: 1px;
    color: inherit;
    max-width: 100%;
    word-wrap: break-word;
}

.btn-palpite.active {
    transform: scale(1.02);
    z-index: 1;
    border-color: currentColor;
    opacity: 1;
}

.btn-palpite:not(.active) {
    opacity: 0.9;
}

/* Estado normal para todos os botões (não ativos) */
.btn-palpite.btn-outline-success,
.btn-palpite.btn-outline-warning,
.btn-palpite.btn-outline-danger {
    color: #495057;
    background-color: rgba(108, 117, 125, 0.1);
    border-color: #6c757d;
}

/* Botão Time Casa (Verde) - apenas hover e ativo */
.btn-palpite.btn-outline-success:hover,
.btn-palpite.btn-outline-success.active {
    color: #fff;
    background-color: #198754;
    border-color: #198754;
    transform: scale(1.02);
    opacity: 1;
    z-index: 1;
}

/* Botão Empate (Amarelo) - apenas hover e ativo */
.btn-palpite.btn-outline-warning:hover,
.btn-palpite.btn-outline-warning.active {
    color: #000;
    background-color: #ffc107;
    border-color: #ffc107;
    transform: scale(1.02);
    opacity: 1;
    z-index: 1;
}

/* Botão Time Visitante (Vermelho) - apenas hover e ativo */
.btn-palpite.btn-outline-danger:hover,
.btn-palpite.btn-outline-danger.active {
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
    transform: scale(1.02);
    opacity: 1;
    z-index: 1;
}

/* Remover regras específicas de cor no estado normal */
.btn-palpite:not(.active) {
    opacity: 0.9;
}

/* Sombras */
.btn-palpite {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-palpite:hover,
.btn-palpite.active {
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.btn-palpite.btn-outline-warning {
    min-width: 70px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-palpite.btn-outline-warning i {
    font-size: 32px; /* Aumentado de 18px */
    margin: 0; /* Remove margem */
}

.game-datetime {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 0.9rem;
    font-weight: 500;
    white-space: nowrap; /* Evita quebra de linha */
}

.date-badge, .time-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 15px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    min-width: fit-content; /* Garante que o conteúdo não seja comprimido */
}

.date-badge {
    background-color: #e9ecef;
}

.time-badge {
    background-color: #fff3cd;
    color: #856404;
}

.date-badge i, .time-badge i {
    font-size: 1rem;
}

/* Animação sutil no hover */
.date-badge:hover, .time-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.bolao-info {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.info-item i {
    font-size: 1.2rem;
    margin-top: 3px;
}

.info-content {
    flex: 1;
}

.info-content label {
    display: block;
    font-weight: 600;
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 2px;
}

.info-content span {
    display: block;
    font-size: 1rem;
}

/* Ajustes para mobile */
@media (max-width: 576px) {
    .card-header h5 {
        font-size: 1rem;
    }

    .card-body h2 {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }

    .bolao-info {
        gap: 8px;
    }

    .info-item {
        gap: 8px;
        background-color: #f8f9fa;
        padding: 8px;
        border-radius: 8px;
        margin-bottom: 2px;
    }

    .info-item i {
        font-size: 1rem;
        margin-top: 2px;
    }

    .info-content label {
        font-size: 0.75rem;
        margin-bottom: 0;
    }

    .info-content span {
        font-size: 0.9rem;
    }

    .badge {
        font-size: 0.7rem;
            padding: 0.2em 0.4em;
}
}

/* Estilos para a seção de informações do bolão */
.info-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(255,255,255,0.2);
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.prize-card {
    transition: all 0.3s ease;
    border: 2px solid rgba(255,255,255,0.3);
}

.prize-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    border-color: rgba(255,255,255,0.5);
}

.stat-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.1);
}

.stat-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    background: #f8f9fa !important;
}

.info-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
}

.info-label {
    font-size: 0.85rem;
    opacity: 0.9;
    margin-bottom: 2px;
}

.info-value {
    font-size: 0.95rem;
    font-weight: 600;
}

/* Animação para o contador regressivo */
.countdown-timer .badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Gradiente animado para o header */
.card-header.bg-gradient {
    position: relative;
    overflow: hidden;
}

.card-header.bg-gradient::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: shine 3s infinite;
}

@keyframes shine {
    0% { left: -100%; }
    100% { left: 100%; }
}
</style>

<script>
// Função para gerar palpites aleatórios
function gerarPalpitesAleatorios() {
    const opcoes = ['casa', 'empate', 'visitante'];
    const radios = document.querySelectorAll('input[type="radio"][name^="resultado_"]');
    
    // Agrupar por jogo
    const jogos = {};
    radios.forEach(radio => {
        const nomeJogo = radio.name;
        if (!jogos[nomeJogo]) {
            jogos[nomeJogo] = [];
        }
        jogos[nomeJogo].push(radio);
    });
    
    // Selecionar uma opção aleatória para cada jogo
    Object.keys(jogos).forEach(nomeJogo => {
        const opcaoAleatoria = opcoes[Math.floor(Math.random() * opcoes.length)];
        const radioEscolhido = jogos[nomeJogo].find(radio => radio.value === opcaoAleatoria);
        if (radioEscolhido && !radioEscolhido.disabled) {
            radioEscolhido.checked = true;
        }
    });
}

// Função para atualizar o contador regressivo
function updateCountdown(element, targetDate) {
    const now = new Date();
    const difference = targetDate - now;
    
    const countdownText = element.querySelector('.countdown-text');
    const badge = element.querySelector('.badge');
    
    if (difference > 0) {
        const days = Math.floor(difference / (1000 * 60 * 60 * 24));
        const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((difference % (1000 * 60)) / 1000);
        
        let displayText = '';
        if (days > 0) {
            displayText = `${days}d ${hours}h ${minutes}m ${seconds}s`;
        } else if (hours > 0) {
            displayText = `${hours}h ${minutes}m ${seconds}s`;
        } else if (minutes > 0) {
            displayText = `${minutes}m ${seconds}s`;
        } else {
            displayText = `${seconds}s`;
        }
        
        countdownText.textContent = displayText;
        badge.className = 'badge bg-success';
    } else {
        countdownText.textContent = 'Prazo encerrado!';
        badge.className = 'badge bg-danger';
        
        // Recarregar a página para atualizar o status após 2 segundos
        setTimeout(() => {
            location.reload();
        }, 2000);
    }
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    // Botões de palpites aleatórios
    const botoes = document.querySelectorAll('#gerarPalpitesAleatorios, #gerarPalpitesAleatorios2');
    botoes.forEach(botao => {
        botao.addEventListener('click', gerarPalpitesAleatorios);
    });
    
    // Inicializar contador regressivo
    const countdownElements = document.querySelectorAll('.countdown-timer');
    countdownElements.forEach(element => {
        const targetDate = new Date(element.dataset.target);
        updateCountdown(element, targetDate);
        
        // Atualizar a cada segundo
        setInterval(() => {
            updateCountdown(element, targetDate);
        }, 1000);
    });
});
</script>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 