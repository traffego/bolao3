<?php
/**
 * Admin Jogos de Bolão - Bolão Vitimba
 */
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/jogos-bolao.php';
    redirect(APP_URL . '/admin/login.php');
}

// Get bolão ID from URL
$bolaoId = isset($_GET['bolao_id']) ? (int)$_GET['bolao_id'] : 0;

if ($bolaoId <= 0) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Get bolão data with JSON fields
$bolao = dbFetchOne(
    "SELECT b.*, a.nome as admin_nome, 
            (SELECT COUNT(*) FROM palpites WHERE bolao_id = b.id AND status = 'pago') as total_palpites,
            (SELECT COUNT(DISTINCT jogador_id) FROM palpites WHERE bolao_id = b.id AND status = 'pago') as total_participantes
     FROM dados_boloes b
     LEFT JOIN administrador a ON a.id = b.admin_id
     WHERE b.id = ?", 
    [$bolaoId]
);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Decode JSON fields
$jogos = json_decode($bolao['jogos'], true) ?? [];
$campeonatos = json_decode($bolao['campeonatos'], true) ?? [];

// Create campeonatos lookup
$campeonatosLookup = [];
foreach ($campeonatos as $campeonato) {
    $campeonatosLookup[$campeonato['id']] = $campeonato['nome'];
}

// Estatísticas dos jogos
$stats = [
    'total' => count($jogos),
    'finalizados' => 0,
    'em_andamento' => 0,
    'agendados' => 0,
    'com_palpites' => 0
];

// Processar status dos jogos
foreach ($jogos as &$jogo) {
    // Converter status da API para nosso formato
    switch ($jogo['status']) {
        case 'FT': // Full Time
            $jogo['status_formatado'] = 'finalizado';
            $stats['finalizados']++;
            break;
        case 'NS': // Not Started
            $jogo['status_formatado'] = 'agendado';
            $stats['agendados']++;
            break;
        case '1H':
        case '2H':
        case 'HT':
            $jogo['status_formatado'] = 'em_andamento';
            $stats['em_andamento']++;
            break;
        default:
            $jogo['status_formatado'] = 'agendado';
            $stats['agendados']++;
    }

    // Adicionar nome do campeonato
    $jogo['campeonato_nome'] = $campeonatosLookup[$jogo['campeonato_id']] ?? $jogo['campeonato'];
}

// Page title
$pageTitle = 'Jogos do Bolão: ' . $bolao['nome'];
$currentPage = 'boloes';

// Include admin header
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <!-- Hero Section -->
    <div class="hero-section bg-success text-white py-4 mb-4" style="border-radius: 24px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="hero-icon me-4">
                            <i class="fas fa-futbol fa-4x"></i>
                        </div>
                        <div>
                            <h1 class="display-4 mb-2"><?= htmlspecialchars($bolao['nome']) ?></h1>
                            <p class="lead mb-0">Gerenciamento de Jogos e Resultados</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-end">
                        <a href="<?= APP_URL ?>/admin/novo-jogo.php?bolao_id=<?= $bolaoId ?>" class="btn btn-light btn-lg me-2">
                            <i class="fas fa-plus"></i> Novo Jogo
                        </a>
                        <a href="<?= APP_URL ?>/admin/boloes.php" class="btn btn-light btn-lg">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-futbol fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['total'] ?></h4>
                        <small>Total de Jogos</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= $stats['finalizados'] ?></h4>
                        <small>Jogos Finalizados</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= intval($bolao['total_participantes']) ?></h4>
                        <small>Participantes</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-green bg-opacity-10 rounded p-3 text-center">
                        <i class="fas fa-list-ol fa-2x mb-2"></i>
                        <h4 class="mb-0"><?= intval($bolao['total_palpites']) ?></h4>
                        <small>Total de Palpites</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php $flashMessage = getFlashMessage(); ?>
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashMessage['type'] ?> alert-dismissible fade show" role="alert">
            <?= $flashMessage['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <!-- Lista de Jogos -->
    <div class="card mb-4 shadow-lg" style="border-radius: 20px; border: none;">
        <div class="card-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 20px 20px 0 0; border: none;">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h4 class="text-white mb-0">
                    <i class="fas fa-list-alt me-2"></i>
                    Lista de Jogos
                </h4>
                <div class="d-flex gap-2">
                    <button class="btn btn-light btn-sm" onclick="refreshGames()">
                        <i class="fas fa-sync-alt"></i> Atualizar
                    </button>
                    <button class="btn btn-light btn-sm" onclick="exportGames()">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                </div>
            </div>
            <ul class="nav nav-tabs card-header-tabs" style="border: none;">
                <li class="nav-item">
                    <a class="nav-link active text-white fw-bold" href="#todos" data-bs-toggle="tab" style="border: none; background: rgba(255,255,255,0.2); border-radius: 10px 10px 0 0;">
                        <i class="fas fa-futbol me-1"></i> Todos os Jogos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="#agendados" data-bs-toggle="tab" style="border: none;">
                        <i class="fas fa-clock me-1"></i> Agendados <span class="badge bg-light text-dark ms-1"><?= $stats['agendados'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="#em-andamento" data-bs-toggle="tab" style="border: none;">
                        <i class="fas fa-play-circle me-1"></i> Em Andamento <span class="badge bg-warning text-dark ms-1"><?= $stats['em_andamento'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="#finalizados" data-bs-toggle="tab" style="border: none;">
                        <i class="fas fa-check-circle me-1"></i> Finalizados <span class="badge bg-light text-dark ms-1"><?= $stats['finalizados'] ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="todos">
                    <?php if (empty($jogos)): ?>
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-futbol fa-5x text-muted opacity-50"></i>
                            </div>
                            <h4 class="text-muted mb-3">Nenhum jogo cadastrado</h4>
                            <p class="text-muted mb-4">Comece adicionando jogos para este bolão</p>
                            <a href="<?= APP_URL ?>/admin/novo-jogo.php?bolao_id=<?= $bolaoId ?>" class="btn btn-success btn-lg">
                                <i class="fas fa-plus me-2"></i>Adicionar Primeiro Jogo
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($jogos as $index => $jogo): ?>
                                <div class="col-12">
                                    <div class="game-card card h-100 shadow-sm" style="border-radius: 15px; border: none; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.1)'">
                                        <div class="card-body p-4">
                                            <div class="row align-items-center">
                                                <!-- Data e Hora -->
                                                <div class="col-lg-2 col-md-3 mb-3 mb-md-0">
                                                    <div class="date-time-card text-center p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px;">
                                                        <?php 
                                                        $dataJogo = isset($jogo['data_iso']) ? $jogo['data_iso'] : $jogo['data'];
                                                        if ($dataJogo): 
                                                        ?>
                                                            <div class="fw-bold text-primary" style="font-size: 0.9rem;"><?= date('d/m/Y', strtotime($dataJogo)) ?></div>
                                                            <div class="text-muted" style="font-size: 1.1rem; font-weight: 600;"><?= date('H:i', strtotime($dataJogo)) ?></div>
                                                        <?php else: ?>
                                                            <div class="fw-bold text-warning">Data não definida</div>
                                                            <div class="text-muted">--:--</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <!-- Times -->
                                                <div class="col-lg-5 col-md-6 mb-3 mb-md-0">
                                                    <div class="teams-container">
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <div class="team-home text-center flex-fill">
                                                                
                                                                <div class="team-name fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($jogo['time_casa']) ?></div>
                                                            </div>
                                                            
                                                            <div class="vs-section text-center mx-3">
                                                                <?php if (isset($jogo['resultado_casa']) && isset($jogo['resultado_visitante'])): ?>
                                                                    <div class="score-display" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; font-size: 1.1rem;">
                                                                        <?= $jogo['resultado_casa'] ?> × <?= $jogo['resultado_visitante'] ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="vs-badge" style="background: #f8f9fa; color: #6c757d; padding: 8px 16px; border-radius: 20px; font-weight: bold;">
                                                                        VS
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <div class="team-away text-center flex-fill">
                                                                
                                                                <div class="team-name fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($jogo['time_visitante']) ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Informações do Jogo -->
                                                <div class="col-lg-3 col-md-3 mb-3 mb-md-0">
                                                    <div class="game-info">
                                                        <div class="mb-2">
                                                            <span class="badge" style="background: linear-gradient(135deg, #6f42c1 0%, #5a2d91 100%); color: white; padding: 6px 12px; border-radius: 20px;">
                                                                <i class="fas fa-trophy me-1"></i>
                                                                <?= htmlspecialchars($jogo['campeonato_nome']) ?>
                                                            </span>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                            <?= htmlspecialchars($jogo['local'] ?? 'Local não definido') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Status e Ações -->
                                                <div class="col-lg-2 text-end">
                                                    <div class="game-status mb-3">
                                                        <?php if ($jogo['status_formatado'] === 'agendado'): ?>
                                                            <span class="badge bg-primary" style="padding: 8px 16px; border-radius: 20px; font-size: 0.85rem;">
                                                                <i class="fas fa-clock me-1"></i>Agendado
                                                            </span>
                                                        <?php elseif ($jogo['status_formatado'] === 'em_andamento'): ?>
                                                            <span class="badge bg-warning" style="padding: 8px 16px; border-radius: 20px; font-size: 0.85rem;">
                                                                <i class="fas fa-play me-1"></i>Em andamento
                                                            </span>
                                                        <?php elseif ($jogo['status_formatado'] === 'finalizado'): ?>
                                                            <span class="badge bg-success" style="padding: 8px 16px; border-radius: 20px; font-size: 0.85rem;">
                                                                <i class="fas fa-check me-1"></i>Finalizado
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger" style="padding: 8px 16px; border-radius: 20px; font-size: 0.85rem;">
                                                                <i class="fas fa-times me-1"></i>Cancelado
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="game-actions">
                                                        <div class="btn-group-vertical" role="group">
                                                            <button class="btn btn-outline-primary btn-sm" onclick="viewGameDetails(<?= $index ?>)" title="Ver detalhes">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-outline-success btn-sm" onclick="editGame(<?= $index ?>)" title="Editar jogo">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Outras abas serão preenchidas via JavaScript -->
                <div class="tab-pane fade" id="agendados"></div>
                <div class="tab-pane fade" id="em-andamento"></div>
                <div class="tab-pane fade" id="finalizados"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Função para filtrar jogos por status
    function filtrarJogos(status) {
        const container = document.querySelector('#todos .row').cloneNode(true);
        const gameCards = container.querySelectorAll('.game-card');
        
        gameCards.forEach(card => {
            // Pegar o badge de status
            const statusBadge = card.querySelector('.game-status .badge');
            let statusJogo = '';
            
            if (statusBadge) {
                statusJogo = statusBadge.textContent.trim();
            }
            
            // Remover o card se não corresponder ao status
            if (status !== 'todos') {
                const statusMatch = {
                    'agendados': 'Agendado',
                    'em-andamento': 'Em andamento',
                    'finalizados': 'Finalizado'
                };
                
                if (!statusJogo.includes(statusMatch[status])) {
                    card.closest('.col-12').remove();
                }
            }
        });
        
        // Se não houver jogos após a filtragem, mostrar mensagem
        if (container.querySelectorAll('.game-card').length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-search fa-3x text-muted opacity-50"></i>
                        </div>
                        <h5 class="text-muted mb-3">Nenhum jogo ${status.replace('-', ' ')} encontrado</h5>
                        <p class="text-muted">Tente filtrar por outro status</p>
                    </div>
                </div>
            `;
        }
        
        return container;
    }
    
    // Preencher as outras abas quando clicadas
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const tab = this.getAttribute('href').substring(1);
            const container = document.querySelector(`#${tab}`);
            
            // Atualizar estilo da aba ativa
            document.querySelectorAll('.nav-link').forEach(l => {
                l.classList.remove('active');
                l.style.background = 'transparent';
            });
            this.classList.add('active');
            this.style.background = 'rgba(255,255,255,0.2)';
            this.style.borderRadius = '10px 10px 0 0';
            
            if (tab !== 'todos' && container.children.length === 0) {
                container.appendChild(filtrarJogos(tab));
            }
        });
    });
});

// Funções auxiliares
function refreshGames() {
    // Implementar atualização dos jogos
    console.log('Atualizando jogos...');
    // Aqui você pode adicionar uma chamada AJAX para atualizar os dados
}

function exportGames() {
    // Implementar exportação dos jogos
    console.log('Exportando jogos...');
    // Aqui você pode adicionar funcionalidade de exportação
}

function viewGameDetails(index) {
    // Implementar visualização de detalhes do jogo
    console.log('Visualizando detalhes do jogo:', index);
    // Aqui você pode abrir um modal ou redirecionar para página de detalhes
}

function editGame(index) {
    // Implementar edição do jogo
    console.log('Editando jogo:', index);
    // Aqui você pode redirecionar para página de edição
}
</script>

<?php include '../templates/admin/footer.php'; ?>