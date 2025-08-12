<?php
/**
 * Bolões Page - Bolão Vitimba
 */
require_once 'config/config.php';require_once 'includes/functions.php';

// Buscar bolões ativos e públicos 
// (se o usuário estiver logado, também mostrar bolões privados que ele participa)
$condition = "status = 1";
$params = [];

// Mostrar apenas bolões públicos (simplificando a lógica)
$condition .= " AND publico = 1";

// Buscar bolões ativos
$sql = "SELECT b.*, 
        (SELECT COUNT(*) FROM palpites p WHERE p.bolao_id = b.id AND p.status = 'pago') as total_participantes
        FROM dados_boloes b 
        WHERE " . $condition . " 
        ORDER BY b.data_criacao DESC";
$boloes = dbFetchAll($sql, $params);

// Título da página
$pageTitle = "Bolões Disponíveis";
include TEMPLATE_DIR . '/header.php';
?>

<div class="container mt-5">
    <h1>Bolões Disponíveis</h1>
    
    <!-- Mensagem flash -->
    <?php displayFlashMessages(); ?>
    
    <div class="row g-4 mt-3">
        <?php if (empty($boloes)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle fs-3 mb-3 d-block"></i>
                    <h5>Nenhum bolão disponível</h5>
                    <p class="mb-0">Não há bolões disponíveis no momento. Volte mais tarde!</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($boloes as $bolao): ?>
                <?php 
                    // Decodificar dados JSON
                    $campeonatos = json_decode($bolao['campeonatos'], true) ?: [];
                    
                    // Verificar se já passou da data limite
                    $prazoEncerrado = false;
                    if (!empty($bolao['data_limite_palpitar'])) {
                        $dataLimite = new DateTime($bolao['data_limite_palpitar']);
                        $agora = new DateTime();
                        $prazoEncerrado = $agora > $dataLimite;
                    }
                ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="bolao-card h-100 <?= $prazoEncerrado ? 'border-danger' : '' ?>">
                        <div class="bolao-card-header" <?php if (!empty($bolao['imagem_bolao_url'])): ?>style="background-image: url('<?= APP_URL ?>/<?= htmlspecialchars($bolao['imagem_bolao_url']) ?>');"<?php endif; ?>>
                            <div class="bolao-header-overlay"></div>
                            <div class="bolao-prize-badge">
                                <i class="bi bi-trophy-fill"></i>
                                <span><?= formatMoney($bolao['premio_total']) ?></span>
                            </div>
                            <?php if ($prazoEncerrado): ?>
                                <div class="position-absolute top-0 start-0 m-2">
                                    <span class="badge bg-danger">Encerrado</span>
                                </div>
                            <?php endif; ?>
                            <h5 class="bolao-title"><?= htmlspecialchars($bolao['nome']) ?></h5>
                        </div>
                        
                        <div class="bolao-card-body">
                            <div class="bolao-stats">
                                <!-- Prazo -->
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="bi bi-clock-fill"></i>
                                    </div>
                                    <div class="stat-content">
                                        <?php if (!empty($bolao['data_limite_palpitar'])): ?>
                                            <span class="stat-value"><?= formatDateTime($bolao['data_limite_palpitar']) ?></span>
                                            <span class="stat-label">Prazo para Palpitar</span>
                                        <?php else: ?>
                                            <span class="stat-value">Não definido</span>
                                            <span class="stat-label">Prazo para Palpitar</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="stat-row">
                                    <div class="stat-item-half">
                                        <div class="stat-icon">
                                            <i class="bi bi-ticket-fill"></i>
                                        </div>
                                        <div class="stat-content">
                                            <span class="stat-value"><?= formatMoney($bolao['valor_participacao']) ?></span>
                                            <span class="stat-label">Entrada</span>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-item-half">
                                        <div class="stat-icon">
                                            <i class="bi bi-calendar-event"></i>
                                        </div>
                                        <div class="stat-content">
                                            <span class="stat-value"><?= formatDate($bolao['data_fim']) ?></span>
                                            <span class="stat-label">Término</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Campeonatos -->
                                <?php if (!empty($campeonatos)): ?>
                                    <div class="stat-item">
                                        <div class="stat-icon">
                                            <i class="bi bi-trophy"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($campeonatos as $campeonato): ?>
                                                    <span class="badge bg-success">
                                                        <?= htmlspecialchars($campeonato['nome']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <span class="stat-label">Campeonatos</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="bolao-card-footer">
                            <a href="<?= APP_URL ?>/bolao.php?id=<?= $bolao['id'] ?>" class="bolao-btn">
                                <?php if ($prazoEncerrado): ?>
                                    <i class="bi bi-eye-fill"></i>
                                    <span>Ver Resultados</span>
                                <?php else: ?>
                                    <i class="bi bi-play-fill"></i>
                                    <span>Participar Agora</span>
                                <?php endif; ?>
                            </a>
                            <?php if ($bolao['publico'] != 1): ?>
                                <div class="text-center mt-2">
                                    <small class="text-warning">
                                        <i class="bi bi-lock-fill"></i> Bolão Privado
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 