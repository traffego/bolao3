<?php
/**
 * Admin Novo Jogo - Bolão Football
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/novo-jogo.php';
    redirect(APP_URL . '/admin/login.php');
}

// Get bolão ID from URL
$bolaoId = isset($_GET['bolao_id']) ? (int)$_GET['bolao_id'] : 0;

if ($bolaoId <= 0) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Get bolão data
$bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ?", [$bolaoId]);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

$errors = [];
$jogo = [
    'time_casa' => '',
    'time_visitante' => '',
    'data_hora' => '',
    'local' => '',
    'status' => 'agendado',
    'peso' => 1
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $jogo['time_casa'] = trim($_POST['time_casa'] ?? '');
    $jogo['time_visitante'] = trim($_POST['time_visitante'] ?? '');
    $jogo['data_hora'] = trim($_POST['data_hora'] ?? '');
    $jogo['local'] = trim($_POST['local'] ?? '');
    $jogo['status'] = trim($_POST['status'] ?? 'agendado');
    $jogo['peso'] = (int)($_POST['peso'] ?? 1);
    
    // Validation
    if (empty($jogo['time_casa'])) {
        $errors[] = 'O time da casa é obrigatório.';
    }
    
    if (empty($jogo['time_visitante'])) {
        $errors[] = 'O time visitante é obrigatório.';
    }
    
    if ($jogo['time_casa'] === $jogo['time_visitante']) {
        $errors[] = 'Os times não podem ser iguais.';
    }
    
    if (empty($jogo['data_hora'])) {
        $errors[] = 'A data e hora do jogo são obrigatórias.';
    } else {
        // Validate date format and check if it's in the future
        $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $jogo['data_hora']);
        if (!$dateTime) {
            $errors[] = 'Formato de data e hora inválido.';
        }
    }
    
    if ($jogo['peso'] < 1) {
        $jogo['peso'] = 1;
    }
    
    // Check if the game is within the bolão period
    if (empty($errors)) {
        $gameDate = date('Y-m-d', strtotime($jogo['data_hora']));
        $bolaoStart = $bolao['data_inicio'];
        $bolaoEnd = $bolao['data_fim'];
        
        if ($gameDate < $bolaoStart || $gameDate > $bolaoEnd) {
            $errors[] = 'A data do jogo deve estar dentro do período do bolão (' . 
                         formatDate($bolaoStart) . ' a ' . formatDate($bolaoEnd) . ').';
        }
    }
    
    // If no errors, insert the game
    if (empty($errors)) {
        $data = [
            'bolao_id' => $bolaoId,
            'time_casa' => $jogo['time_casa'],
            'time_visitante' => $jogo['time_visitante'],
            'data_hora' => $jogo['data_hora'],
            'local' => $jogo['local'],
            'status' => $jogo['status'],
            'peso' => $jogo['peso']
        ];
        
        $jogoId = dbInsert('jogos', $data);
        
        if ($jogoId) {
            setFlashMessage('success', 'Jogo adicionado com sucesso!');
            redirect(APP_URL . '/admin/jogos-bolao.php?bolao_id=' . $bolaoId);
        } else {
            $errors[] = 'Erro ao adicionar o jogo. Por favor, tente novamente.';
        }
    }
}

// Page title
$pageTitle = 'Adicionar Novo Jogo';
$currentPage = 'boloes';

// Include admin header
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">Adicionar Novo Jogo</h1>
        <div>
            <a href="<?= APP_URL ?>/admin/jogos-bolao.php?bolao_id=<?= $bolaoId ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/boloes.php">Bolões</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/jogos-bolao.php?bolao_id=<?= $bolaoId ?>">Jogos</a></li>
        <li class="breadcrumb-item active">Novo Jogo</li>
    </ol>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-futbol me-1"></i>
            Detalhes do Jogo para o Bolão: <?= htmlspecialchars($bolao['nome']) ?>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="time_casa" class="form-label">Time da Casa <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="time_casa" name="time_casa" 
                               value="<?= htmlspecialchars($jogo['time_casa']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="time_visitante" class="form-label">Time Visitante <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="time_visitante" name="time_visitante" 
                               value="<?= htmlspecialchars($jogo['time_visitante']) ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="data_hora" class="form-label">Data e Hora <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="data_hora" name="data_hora" 
                               value="<?= htmlspecialchars($jogo['data_hora']) ?>" required>
                        <small class="text-muted">
                            O jogo deve estar dentro do período do bolão: 
                            <?= formatDate($bolao['data_inicio']) ?> a <?= formatDate($bolao['data_fim']) ?>
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label for="local" class="form-label">Local</label>
                        <input type="text" class="form-control" id="local" name="local" 
                               value="<?= htmlspecialchars($jogo['local']) ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="agendado" <?= $jogo['status'] === 'agendado' ? 'selected' : '' ?>>Agendado</option>
                            <option value="em_andamento" <?= $jogo['status'] === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
                            <option value="finalizado" <?= $jogo['status'] === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                            <option value="cancelado" <?= $jogo['status'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="peso" class="form-label">Peso do Jogo</label>
                        <input type="number" class="form-control" id="peso" name="peso" 
                               value="<?= htmlspecialchars($jogo['peso']) ?>" min="1">
                        <small class="text-muted">
                            O peso determina a importância do jogo. Pontos serão multiplicados por este valor.
                        </small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Jogo
                    </button>
                    <a href="<?= APP_URL ?>/admin/jogos-bolao.php?bolao_id=<?= $bolaoId ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../templates/admin/footer.php'; ?> 