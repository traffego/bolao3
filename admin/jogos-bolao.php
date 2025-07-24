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
    <div class="card mb-4">
        <div class="card-header bg-green">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link active" href="#todos" data-bs-toggle="tab">Todos os Jogos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#agendados" data-bs-toggle="tab">Agendados (<?= $stats['agendados'] ?>)</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#em-andamento" data-bs-toggle="tab">Em Andamento (<?= $stats['em_andamento'] ?>)</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#finalizados" data-bs-toggle="tab">Finalizados (<?= $stats['finalizados'] ?>)</a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="todos">
                    <?php if (empty($jogos)): ?>
                        <div class="alert alert-info">
                            Nenhum jogo cadastrado para este bolão. 
                            <a href="<?= APP_URL ?>/admin/novo-jogo.php?bolao_id=<?= $bolaoId ?>" class="alert-link">Adicionar jogos</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data/Hora</th>
                                        <th>Campeonato</th>
                                        <th>Times</th>
                                        <th>Local</th>
                                        <th>Status</th>
                                        <th>Resultado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jogos as $jogo): ?>
                                        <tr>
                                            <td style="width: 160px;">
                                                <div class="fw-bold"><?= date('d/m/Y', strtotime($jogo['data_iso'])) ?></div>
                                                <small class="text-muted"><?= date('H:i', strtotime($jogo['data_iso'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($jogo['campeonato_nome']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span class="fw-bold"><?= htmlspecialchars($jogo['time_casa']) ?></span>
                                                    <span class="badge bg-light text-dark mx-2">VS</span>
                                                    <span class="fw-bold"><?= htmlspecialchars($jogo['time_visitante']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars($jogo['local'] ?? 'N/A') ?></small>
                                            </td>
                                            <td>
                                                <?php if ($jogo['status_formatado'] === 'agendado'): ?>
                                                    <span class="badge bg-primary">Agendado</span>
                                                <?php elseif ($jogo['status_formatado'] === 'em_andamento'): ?>
                                                    <span class="badge bg-warning">Em andamento</span>
                                                <?php elseif ($jogo['status_formatado'] === 'finalizado'): ?>
                                                    <span class="badge bg-success">Finalizado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Cancelado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="width: 120px;">
                                                <?php if (isset($jogo['resultado_casa']) && isset($jogo['resultado_visitante'])): ?>
                                                    <div class="text-center">
                                                        <span class="fw-bold"><?= $jogo['resultado_casa'] ?> x <?= $jogo['resultado_visitante'] ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
        const tabela = document.querySelector('#todos table').cloneNode(true);
        const tbody = tabela.querySelector('tbody');
        const linhas = tbody.querySelectorAll('tr');
        
        linhas.forEach(linha => {
            // Pegar o badge de status (ignorando o badge do campeonato)
            const badges = linha.querySelectorAll('.badge');
            let statusJogo = '';
            
            // Procurar o badge correto (que não seja do campeonato ou do VS)
            badges.forEach(badge => {
                if (!badge.classList.contains('bg-secondary') && !badge.classList.contains('bg-light')) {
                    statusJogo = badge.textContent.trim();
                }
            });
            
            // Remover a linha se não corresponder ao status
            if (status !== 'todos') {
                const statusMatch = {
                    'agendados': 'Agendado',
                    'em-andamento': 'Em andamento',
                    'finalizados': 'Finalizado'
                };
                
                if (statusJogo !== statusMatch[status]) {
                    linha.remove();
                }
            }
        });
        
        // Se não houver jogos após a filtragem, mostrar mensagem
        if (tbody.querySelectorAll('tr').length === 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td colspan="6" class="text-center py-3">
                    <div class="alert alert-info mb-0">
                        Nenhum jogo ${status.replace('-', ' ')} encontrado.
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        }
        
        return tabela;
    }
    
    // Preencher as outras abas quando clicadas
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const tab = this.getAttribute('href').substring(1);
            const container = document.querySelector(`#${tab}`);
            
            if (tab !== 'todos' && container.children.length === 0) {
                container.appendChild(filtrarJogos(tab));
            }
        });
    });
});
</script>

<?php include '../templates/admin/footer.php'; ?> 