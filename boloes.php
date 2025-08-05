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
    
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">
        <?php if (empty($boloes)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    Nenhum bolão disponível no momento.
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
                <div class="col">
                    <div class="card h-100 <?= $prazoEncerrado ? 'border-danger' : '' ?>">
                        <?php if (!empty($bolao['imagem_bolao_url'])): ?>
                            <img src="<?= APP_URL ?>/<?= htmlspecialchars($bolao['imagem_bolao_url']) ?>" 
                                 class="card-img-top" 
                                 alt="<?= htmlspecialchars($bolao['nome']) ?>"
                                 style="object-fit: cover; height: 150px;">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($bolao['nome']) ?></h5>
                            
                            <p class="text-muted mb-2">
                                <i class="fas fa-calendar"></i> 
                                <?= formatDate($bolao['data_inicio']) ?> a <?= formatDate($bolao['data_fim']) ?>
                            </p>
                            
                            <p class="mb-2">
                                <i class="fas fa-clock"></i> 
                                <strong>Prazo:</strong> 
                                <?php if (!empty($bolao['data_limite_palpitar'])): ?>
                                    <?= formatDateTime($bolao['data_limite_palpitar']) ?>
                                    <?php if ($prazoEncerrado): ?>
                                        <span class="badge bg-danger">Encerrado</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Não definido
                                <?php endif; ?>
                            </p>
                            
                            <p class="mb-2">
                                <i class="fas fa-money-bill-wave"></i> 
                                <strong>Valor:</strong> <?= formatMoney($bolao['valor_participacao']) ?>
                            </p>
                            
                            <p class="mb-2">
                                <i class="fas fa-trophy"></i> 
                                <strong>Prêmio:</strong> <?= formatMoney($bolao['premio_total']) ?>
                            </p>
                            
                            <div class="mb-3">
                                <?php foreach ($campeonatos as $campeonato): ?>
                                    <span class="badge bg-info text-dark">
                                        <?= htmlspecialchars($campeonato['nome']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            
                            <a href="<?= APP_URL ?>/bolao.php?id=<?= $bolao['id'] ?>" class="btn btn-primary w-100">
                                <?php if ($prazoEncerrado): ?>
                                    Ver Resultados 
                                <?php else: ?>
                                    Participar
                                <?php endif; ?>
                            </a>
                        </div>
                        
                        <?php if ($bolao['publico'] != 1): ?>
                            <div class="card-footer bg-warning text-center">
                                <small class="text-dark"><i class="fas fa-lock"></i> Bolão Privado</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 