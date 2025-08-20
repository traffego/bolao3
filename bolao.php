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
else if (isset($_SESSION['palpites_temp'])) {
    // Verificar se é um array de palpites preservados após depósito
    if (isset($_SESSION['palpites_temp']['bolao_id']) && $_SESSION['palpites_temp']['bolao_id'] == $bolao['id']) {
        // Restaurar palpites preservados
        $palpitesSessao = [];
        foreach ($_SESSION['palpites_temp'] as $key => $value) {
            if (strpos($key, 'resultado_') === 0) {
                $jogoId = substr($key, strlen('resultado_'));
                $palpitesSessao[$jogoId] = $value;
            }
        }
        
        // Limpar flag de saldo insuficiente se existir e mostrar mensagem
        if (isset($_SESSION['saldo_insuficiente'])) {
            unset($_SESSION['saldo_insuficiente']);
            $mensagem = [
                'tipo' => 'success',
                'texto' => 'Seus palpites foram restaurados! Agora você pode continuar de onde parou.'
            ];
        }
    }
    // Formato antigo de palpites temporários
    else if (isset($_SESSION['palpites_temp'][$bolao['id']])) {
        $palpitesSessao = $_SESSION['palpites_temp'][$bolao['id']];
    }
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
        <div class="card shadow-sm border-0 mobile-info-card">
            <div class="card-header text-white" style="background: var(--gradient-primary, linear-gradient(135deg, #1e3c72 0%, #3498db 100%));">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-trophy-fill me-2" style="font-size: 1.2rem;"></i>
                        <div>
                            <h5 class="card-title mb-0 fw-bold" style="font-family: var(--font-primary);">Informações do Bolão</h5>
                            <small class="d-md-none" id="bolaoSummary" style="opacity: 0.8;">Toque para ver detalhes</small>
                        </div>
                    </div>
                    <button class="btn btn-sm text-white d-md-none" id="toggleBolaoInfo" type="button" style="border: none; background: transparent;">
                        <i class="bi bi-chevron-up" id="toggleIcon" style="font-size: 1.2rem;"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-3" id="bolaoInfoContent">
                <!-- Título do Bolão - Compacto -->
                <div class="text-center mb-3">
                    <h4 class="fw-bold mb-1 text-truncate" style="color: var(--globo-verde-principal, #06AA48); font-size: 1.1rem;"><?= htmlspecialchars($bolao['nome']) ?></h4>
                    <?php if (!empty($bolao['descricao'])): ?>
                        <p class="text-muted mb-0" style="font-size: 0.8rem; line-height: 1.3;"><?= htmlspecialchars(substr($bolao['descricao'], 0, 80)) ?><?= strlen($bolao['descricao']) > 80 ? '...' : '' ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Grid Compacto de Informações -->
                <div class="bolao-info-grid mb-3">
                    <!-- Período -->
                    <div class="info-item">
                        <div class="info-content">
                            <span class="info-label">Período</span>
                            <span class="info-value"><?= date('d/m', strtotime($bolao['data_inicio'])) ?> - <?= date('d/m', strtotime($bolao['data_fim'])) ?></span>
                        </div>
                    </div>

                    <!-- Prazo para Palpites -->
                    <?php if ($dataLimite): ?>
                    <div class="info-item <?= $prazoEncerrado ? 'expired' : 'active' ?>">
                        <div class="info-content">
                            <span class="info-label"><?= $prazoEncerrado ? 'Encerrado' : 'Prazo' ?></span>
                            <span class="info-value"><?= $dataLimite->format('d/m H:i') ?></span>
                            <?php if (!$prazoEncerrado): ?>
                                <div class="countdown-compact" data-target="<?= $dataLimite->format('Y-m-d H:i:s') ?>">
                                    <span class="countdown-text">...</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Valor de Participação -->
                    <?php if ($bolao['valor_participacao'] > 0): ?>
                    <div class="info-item">
                        <div class="info-content">
                            <span class="info-label">Entrada</span>
                            <span class="info-value money"><?= formatMoney($bolao['valor_participacao']) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Saldo do Usuário -->
                    <?php if (isLoggedIn() && getModeloPagamento() === 'conta_saldo' && $saldoInfo): ?>
                    <div class="info-item <?= $podeApostar ? 'success' : 'danger' ?>">
                        <div class="info-content">
                            <span class="info-label">Saldo</span>
                            <span class="info-value money"><?= formatMoney($saldoInfo['saldo_atual']) ?></span>
                            <?php if (!$podeApostar): ?>
                                <span class="status-badge insufficient">Insuficiente</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Total de Jogos -->
                    <div class="info-item">
                        <div class="info-content">
                            <span class="info-label">Jogos</span>
                            <span class="info-value"><?= count($jogos) ?></span>
                        </div>
                    </div>

                    <!-- Máximo de Participantes -->
                    <?php if ($bolao['max_participantes'] > 0): ?>
                    <div class="info-item">
                        <div class="info-content">
                            <span class="info-label">Máx.</span>
                            <span class="info-value"><?= $bolao['max_participantes'] ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Prêmios - Compacto -->
                <?php if ($bolao['premio_total'] > 0 || $bolao['premio_rodada'] > 0): ?>
                <div class="prizes-section mb-3">
                    <div class="section-title">
                        <i class="bi bi-trophy-fill"></i>
                        <span>Prêmios</span>
                    </div>
                    <div class="prizes-grid">
                        <?php if ($bolao['premio_total'] > 0): ?>
                        <div class="prize-item total">
                            <span class="prize-label">Total</span>
                            <span class="prize-value"><?= formatMoney($bolao['premio_total']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($bolao['premio_rodada'] > 0): ?>
                        <div class="prize-item rodada">
                            <span class="prize-label">Rodada</span>
                            <span class="prize-value"><?= formatMoney($bolao['premio_rodada']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Aviso de Saldo Insuficiente - Compacto -->
                <?php if (isLoggedIn() && !$podeApostar && getModeloPagamento() === 'conta_saldo'): ?>
                <div class="alert-compact warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div class="alert-content">
                        <span class="alert-title">Saldo Insuficiente</span>
                        <a href="<?= APP_URL ?>/minha-conta.php" class="alert-action">Depositar</a>
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
                    <div class="card mb-4" style="border: 2px solid var(--globo-laranja-energia, #f39c12);">
                        <div class="card-body" style="background: linear-gradient(135deg, rgba(243, 156, 18, 0.1) 0%, rgba(230, 126, 34, 0.1) 100%);">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-clock-history me-3" style="font-size: 2rem; margin-top: 0.25rem; color: var(--globo-laranja-energia, #f39c12);"></i>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-2" style="color: var(--globo-laranja-energia, #f39c12); font-family: var(--font-primary); font-weight: 700;">
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
                    <div class="card mb-3 border-0 bg-transparent">
                        <div class="card-body p-2 d-flex align-items-center justify-content-between flex-column flex-md-row">
                            <div class="mb-2 mb-md-0 text-center text-md-start">
                                <h6 class="card-title mb-1 d-none d-md-block">
                                    <i class="bi bi-dice-5-fill text-primary me-2"></i>
                                    Quer ajuda com os palpites?
                                </h6>
                                <p class="card-text text-muted mb-0 small d-none d-md-block">
                                    Clique no botão ao lado para gerar palpites aleatórios
                                </p>
                                <span class="d-md-none small text-muted">Gerar palpites aleatórios</span>
                            </div>
                            <button type="button" 
                                    class="btn btn-sm w-100 w-md-auto" 
                                    onclick="gerarPalpitesAleatorios(this)"
                                    data-bs-toggle="tooltip" 
                                    data-bs-placement="top" 
                                    title="Gera resultados aleatórios para todos os jogos"
                                    style="background: var(--gradient-danger, linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)); color: white; border: none; font-family: var(--font-primary); font-weight: 600;">
                                <i class="bi bi-shuffle me-1"></i>
                                <span class="d-none d-md-inline">Gerar Palpites</span>
                                <span class="d-md-none">Gerar</span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php 
                // Agrupar jogos por data
                $jogosPorData = [];
                foreach ($jogos as $jogo) {
                    $dataJogo = !empty($jogo['data_formatada']) ? substr($jogo['data_formatada'], 0, 10) : date('d/m/Y', strtotime($jogo['data']));
                    $jogosPorData[$dataJogo][] = $jogo;
                }
                ?>
                
                <?php foreach ($jogosPorData as $data => $jogosData): ?>
                    <!-- Cabeçalho da Data -->
                    <div class="date-header mb-3">
                        <div class="d-flex align-items-center">
                            <div class="date-line flex-grow-1"></div>
                            <div class="date-badge-header mx-3">
                                <i class="bi bi-calendar-day me-2"></i>
                                <?= $data ?>
                            </div>
                            <div class="date-line flex-grow-1"></div>
                        </div>
                    </div>
                    
                    <?php foreach ($jogosData as $jogo): ?>
                        <?php 
                        $jogoId = $jogo['id'];
                        $palpiteJogo = $palpites[$jogoId] ?? $palpitesSessao[$jogoId] ?? null;
                        $disabled = $prazoEncerrado ? 'disabled' : '';
                        ?>
                    <div class="card mb-2 border-0 bg-transparent shadow-none <?= $prazoEncerrado ? 'opacity-75' : '' ?>">
                        <div class="card-body py-1 <?= $prazoEncerrado ? 'bg-light' : '' ?>">
                            <?php if ($prazoEncerrado): ?>
                                <div class="position-relative">
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <i class="bi bi-lock-fill text-muted" title="Prazo encerrado - não é mais possível palpitar neste jogo"></i>
                                    </div>
                            <?php endif; ?>
                            <div class="row align-items-center">
                                <div class="col-md-12">
                                    <div class="palpites-container">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <div class="time-badge-left me-3">
                                                <span class="time-badge">
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
                                                    <img src="<?= $jogo['logo_time_casa'] ?>" alt="<?= $jogo['nome_time_casa'] ?>" class="team-logo-btn" crossorigin="anonymous" onerror="this.style.display='none'">
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
                                                    <img src="<?= $jogo['logo_time_visitante'] ?>" alt="<?= $jogo['nome_time_visitante'] ?>" class="team-logo-btn" crossorigin="anonymous" onerror="this.style.display='none'">
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
                <?php endforeach; ?>
                
                <div class="d-flex justify-content-start align-items-center mt-4 mb-5">
                    <a href="<?= APP_URL ?>/boloes.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar para Bolões
                    </a>
                </div>
                
                <!-- Botão Flutuante para Salvar Palpites -->
                <?php if (!$prazoEncerrado): ?>
                <div class="fixed-bottom-btn">
                    <button type="submit" class="btn btn-lg btn-floating-save" id="btnSalvarPalpites">
                        <i class="bi bi-check-circle me-2"></i> Salvar Palpites
                    </button>
                </div>
                <?php endif; ?>
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

<!-- Modal de Confirmação de Palpites -->
<div class="modal fade" id="confirmPalpitesModal" tabindex="-1" aria-labelledby="confirmPalpitesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmPalpitesModalLabel">Confirmar Palpites</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="confirmContent">
                    <i class="bi bi-question-circle-fill text-warning" style="font-size: 3rem;"></i>
                    <h4 class="mt-3 mb-3">ESTES SÃO SEUS PALPITES. CONFIRMAR?</h4>
                    <p class="text-muted">Após confirmar, seus palpites serão salvos no sistema.</p>
                </div>
                <div id="loadingContent" style="display: none;">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <h4 class="mt-3">Salvando seus palpites...</h4>
                    <p class="text-muted">Aguarde um momento</p>
                </div>
            </div>
            <div class="modal-footer justify-content-center" id="confirmButtons">
                <button type="button" class="btn btn-outline-secondary btn-lg px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Cancelar
                </button>
                <button type="button" class="btn btn-success btn-lg px-4" id="btnConfirmarPalpites">
                    <i class="bi bi-check-circle me-2"></i>Confirmar
                </button>
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
        
        // Após validação dos palpites, verificar outras condições
        <?php if (!isLoggedIn()): ?>
            const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            loginModal.show();
        <?php elseif (!$podeApostar && getModeloPagamento() === 'conta_saldo'): ?>
            window.location.href = '<?= APP_URL ?>/minha-conta.php';
        <?php else: ?>
            // Mostrar modal de confirmação
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmPalpitesModal'));
            confirmModal.show();
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
                // Fechar modal de login
                const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                loginModal.hide();
                
                // Aguardar o modal fechar completamente antes de mostrar confirmação
                document.getElementById('loginModal').addEventListener('hidden.bs.modal', function() {
                    // Agora que o usuário está logado, mostrar modal de confirmação diretamente
                    const confirmModal = new bootstrap.Modal(document.getElementById('confirmPalpitesModal'));
                    confirmModal.show();
                }, { once: true });
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
                // Fechar modal de login
                const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                loginModal.hide();
                
                // Aguardar o modal fechar completamente antes de mostrar confirmação
                document.getElementById('loginModal').addEventListener('hidden.bs.modal', function() {
                    // Agora que o usuário está registrado e logado, mostrar modal de confirmação diretamente
                    const confirmModal = new bootstrap.Modal(document.getElementById('confirmPalpitesModal'));
                    confirmModal.show();
                }, { once: true });
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
            
            // Verificar se há palpites selecionados para mostrar/ocultar botão flutuante
            updateFloatingButton();
        });
    });
    
    // Adicionar evento de mudança para os radio buttons (para capturar cliques diretos nos inputs)
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
    

    
    // Inicializar estado do botão flutuante
    updateFloatingButton();
    
    // Toggle para informações do bolão no mobile
    const toggleBtn = document.getElementById('toggleBolaoInfo');
    const bolaoInfoContent = document.getElementById('bolaoInfoContent');
    const toggleIcon = document.getElementById('toggleIcon');
    const bolaoSummary = document.getElementById('bolaoSummary');
    
    if (toggleBtn && bolaoInfoContent && toggleIcon) {
        // Iniciar com o conteúdo recolhido no mobile
        if (window.innerWidth <= 767) {
            bolaoInfoContent.classList.add('collapsed');
            toggleIcon.classList.add('rotated');
            if (bolaoSummary) bolaoSummary.textContent = 'Toque para ver detalhes';
        }
        
        toggleBtn.addEventListener('click', function() {
            const isCollapsed = bolaoInfoContent.classList.contains('collapsed');
            
            bolaoInfoContent.classList.toggle('collapsed');
            toggleIcon.classList.toggle('rotated');
            
            // Atualizar texto do resumo
            if (bolaoSummary) {
                if (isCollapsed) {
                    bolaoSummary.textContent = 'Toque para ocultar';
                } else {
                    bolaoSummary.textContent = 'Toque para ver detalhes';
                }
            }
            
            // Feedback visual no botão
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
        
        // Verificar redimensionamento da janela
        window.addEventListener('resize', function() {
            if (window.innerWidth > 767) {
                // Desktop: sempre mostrar
                bolaoInfoContent.classList.remove('collapsed');
                toggleIcon.classList.remove('rotated');
            } else if (!bolaoInfoContent.classList.contains('collapsed')) {
                // Mobile: manter estado atual se já estiver expandido
                if (bolaoSummary) bolaoSummary.textContent = 'Toque para ocultar';
            } else {
                if (bolaoSummary) bolaoSummary.textContent = 'Toque para ver detalhes';
            }
        });
    }
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
            
            // Atualizar botão flutuante
            updateFloatingButton();
            
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
    max-width: 500px;
    flex-direction: row; /* Força layout horizontal */
    flex-wrap: nowrap; /* Impede quebra de linha */
}

.palpites-container .d-flex {
    max-width: 600px;
    margin: 0 auto;
}

.palpites-buttons .btn-palpite {
    flex: 1 1 0; /* Distribuição igual do espaço */
    min-width: 0; /* Permite que os botões encolham */
    height: 45px; /* Reduzido ainda mais para 45px */
    transition: all 0.3s ease;
    border-radius: 0;
    margin: 0;
    padding: 2px; /* Reduzido de 3px para 2px */
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1px; /* Reduzido de 2px para 1px */
    border: none;
    opacity: 0.9;
    color: #495057;
    background-color: rgba(108, 117, 125, 0.1);
}

/* Botão da esquerda */
.palpites-buttons .btn-palpite:first-child {
    border-radius: 8px 0 0 8px;
}

/* Botão do meio */
.palpites-buttons .btn-palpite:not(:first-child):not(:last-child) {
    border-radius: 0;
}

/* Botão da direita */
.palpites-buttons .btn-palpite:last-child {
    border-radius: 0 8px 8px 0;
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
        width: 24px; /* Logos ainda menores no mobile */
        height: 24px;
    }

    .team-name-btn {
        font-size: 7px; /* Fonte ainda menor no mobile */
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .date-badge-header {
        font-size: 0.8rem;
        padding: 3px 10px;
    }
    
    .date-header {
        margin: 1.5rem 0 0.75rem 0;
    }
    
    .time-badge {
        font-size: 0.65rem;
        padding: 1px 4px;
    }
    
    .time-badge i {
        font-size: 0.6rem;
    }
    
    .time-badge-left {
        min-width: 50px;
    }
    
    .palpites-container .d-flex {
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }
    
    .time-badge-left {
        margin-bottom: 0.25rem;
    }
    
    .palpites-buttons {
        max-width: 100%;
    }

    .btn-palpite.btn-outline-warning i {
        font-size: 24px; /* X um pouco menor no mobile */
    }
}

/* Mantém os outros estilos... */
.team-logo-btn {
    width: 28px; /* Reduzido ainda mais para 28px */
    height: 28px; /* Reduzido ainda mais para 28px */
    object-fit: contain;
    margin-bottom: 1px;
}

.team-name-btn {
    font-size: 8px; /* Reduzido ainda mais para 8px */
    text-align: center;
    line-height: 1.0;
    font-weight: bold;
    margin-top: 1px;
    color: inherit;
    max-width: 100%;
    word-wrap: break-word;
}

.btn-palpite.active {
    transform: scale(1.02);
    z-index: 1;
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
    border: none;
}

/* Botão Time Casa (Verde) - apenas hover e ativo */
.btn-palpite.btn-outline-success:hover,
.btn-palpite.btn-outline-success.active {
    color: #fff;
    background-color: #198754;
    border: none;
    transform: scale(1.02);
    opacity: 1;
    z-index: 1;
}

/* Botão Empate (Amarelo) - apenas hover e ativo */
.btn-palpite.btn-outline-warning:hover,
.btn-palpite.btn-outline-warning.active {
    color: #000;
    background-color: #ffc107;
    border: none;
    transform: scale(1.02);
    opacity: 1;
    z-index: 1;
}

/* Botão Time Visitante (Vermelho) - apenas hover e ativo */
.btn-palpite.btn-outline-danger:hover,
.btn-palpite.btn-outline-danger.active {
    color: #fff;
    background-color: #dc3545;
    border: none;
    transform: scale(1.02);
    opacity: 1;
    z-index: 1;
}

/* Remover regras específicas de cor no estado normal */
.btn-palpite:not(.active) {
    opacity: 0.9;
}

/* Sombras removidas para visual mais limpo */
.btn-palpite {
    box-shadow: none;
}

.btn-palpite:hover,
.btn-palpite.active {
    box-shadow: none;
}

.btn-palpite.btn-outline-warning {
    min-width: 70px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-palpite.btn-outline-warning i {
    font-size: 24px; /* Reduzido de 32px para 24px */
    margin: 0; /* Remove margem */
}

/* Cabeçalhos de data */
.date-header {
    margin: 2rem 0 1rem 0;
}

.date-badge-header {
    background: linear-gradient(135deg, var(--globo-verde-principal, #06AA48) 0%, var(--globo-verde-claro, #4CAF50) 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    white-space: nowrap;
}

.date-line {
    height: 2px;
    background: linear-gradient(to right, transparent, var(--globo-verde-principal, #06AA48), transparent);
    opacity: 0.3;
}

.time-badge-left {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    min-width: 60px;
}

.game-time {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 500;
}

.time-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 6px;
    border-radius: 8px;
    background-color: rgba(255, 243, 205, 0.6);
    color: #856404;
    font-size: 0.7rem;
    font-weight: 400;
    opacity: 0.8;
}

.time-badge i {
    font-size: 0.65rem;
}

/* Animação sutil no hover */
.time-badge:hover {
    transform: translateY(-1px);
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

/* Botão Flutuante de Salvar Palpites */
.fixed-bottom-btn {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999; /* Aumentado para garantir que fique acima de tudo */
    display: block; /* Sempre visível */
    animation: slideUpBounce 0.8s ease-out;
}

.btn-floating-save {
    background: var(--gradient-success, linear-gradient(135deg, #27ae60 0%, #2ecc71 100%)) !important;
    color: white !important;
    border: none !important;
    font-family: var(--font-primary);
    font-weight: 600;
    padding: 12px 30px;
    border-radius: 50px;
    box-shadow: 0 4px 20px rgba(39, 174, 96, 0.4);
    transition: all 0.3s ease;
    min-width: 200px;
    position: relative;
    z-index: 10000;
}

.btn-floating-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(39, 174, 96, 0.5);
    color: white;
}

.btn-floating-save:active {
    transform: translateY(0);
    box-shadow: 0 2px 15px rgba(39, 174, 96, 0.3);
}

/* Estados do botão flutuante */
.btn-floating-save.btn-warning {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%) !important;
    box-shadow: 0 4px 20px rgba(243, 156, 18, 0.4) !important;
}

.btn-floating-save.btn-warning:hover {
    box-shadow: 0 6px 25px rgba(243, 156, 18, 0.5) !important;
}

.btn-floating-save.btn-success {
    background: var(--gradient-success, linear-gradient(135deg, #27ae60 0%, #2ecc71 100%)) !important;
    box-shadow: 0 4px 20px rgba(39, 174, 96, 0.4) !important;
}

.btn-floating-save.btn-success:hover {
    box-shadow: 0 6px 25px rgba(39, 174, 96, 0.5) !important;
}

/* Toggle de informações do bolão no mobile */
@media (max-width: 767.98px) {
    #bolaoInfoContent {
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    #bolaoInfoContent.collapsed {
        max-height: 0;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        opacity: 0;
    }
    
    #bolaoInfoContent:not(.collapsed) {
        max-height: 1000px; /* Altura máxima para a animação */
        opacity: 1;
    }
    
    #toggleIcon {
        transition: transform 0.3s ease;
    }
    
    #toggleIcon.rotated {
        transform: rotate(180deg);
    }
    
    /* Estilo do botão toggle */
    #toggleBolaoInfo {
        transition: all 0.2s ease;
    }
    
    #toggleBolaoInfo:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        border-radius: 50%;
    }
}

/* Ajustes para mobile sem Hero Banner */
@media (max-width: 767.98px) {
    .mobile-info-card {
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .mobile-info-card .card-header {
        border-radius: 15px 15px 0 0 !important;
        padding: 0.75rem 1rem;
    }
    
    .mobile-info-card .card-title {
        font-size: 1rem !important;
    }
    
    .mobile-info-card #bolaoSummary {
        font-size: 0.75rem;
    }
}

@keyframes slideUpBounce {
    0% {
        opacity: 0;
        transform: translateX(-50%) translateY(100px);
    }
    60% {
        opacity: 1;
        transform: translateX(-50%) translateY(-10px);
    }
    80% {
        transform: translateX(-50%) translateY(5px);
    }
    100% {
        transform: translateX(-50%) translateY(0);
    }
}

/* Garantir visibilidade do botão */
.fixed-bottom-btn.show {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Responsivo para mobile */
@media (max-width: 576px) {
    .fixed-bottom-btn {
        left: 15px !important;
        right: 15px !important;
        transform: none !important;
        width: auto !important;
    }
    
    .btn-floating-save {
        width: 100% !important;
        min-width: auto !important;
        padding: 15px 20px !important;
    }
}
</style>

<script>

// Função global para atualizar texto do botão flutuante
function updateFloatingButton() {
    const floatingBtn = document.querySelector('.fixed-bottom-btn');
    console.log('updateFloatingButton chamada, botão encontrado:', !!floatingBtn);
    
    if (!floatingBtn) return;
    
    const selectedPalpites = document.querySelectorAll('input[type="radio"]:checked').length;
    const totalJogos = <?= count($jogos) ?>;
    
    console.log('Palpites selecionados:', selectedPalpites, 'de', totalJogos);
    
    // Botão sempre visível, apenas muda o texto e estilo
    floatingBtn.classList.add('show');
    
    // Atualizar texto do botão com progresso
    const btnText = document.querySelector('#btnSalvarPalpites');
    if (btnText) {
        if (selectedPalpites === 0) {
            btnText.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i>Selecione os Palpites (0/' + totalJogos + ')';
            btnText.classList.add('btn-warning');
            btnText.classList.remove('btn-success');
        } else if (selectedPalpites === totalJogos) {
            btnText.innerHTML = '<i class="bi bi-check-circle me-2"></i>Salvar Palpites (' + selectedPalpites + '/' + totalJogos + ')';
            btnText.classList.add('btn-success');
            btnText.classList.remove('btn-warning');
        } else {
            btnText.innerHTML = '<i class="bi bi-clock me-2"></i>Palpites (' + selectedPalpites + '/' + totalJogos + ')';
            btnText.classList.add('btn-warning');
            btnText.classList.remove('btn-success');
        }
    }
    console.log('Botão atualizado - sempre visível');
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
    
    // Handle confirmation modal
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

    // Reset modal when closed
    document.getElementById('confirmPalpitesModal').addEventListener('hidden.bs.modal', function() {
        // Restaurar estado inicial do modal
        document.getElementById('confirmContent').style.display = 'block';
        document.getElementById('confirmButtons').style.display = 'flex';
        document.getElementById('loadingContent').style.display = 'none';
    });
});
</script>

<?php include TEMPLATE_DIR . '/footer.php'; ?>