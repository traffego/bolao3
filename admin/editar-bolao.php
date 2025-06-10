<?php
/**
 * Admin Editar Bolão - Bolão Vitimba
 */
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/editar-bolao.php';
    redirect(APP_URL . '/admin/login.php');
}

// Get bolão ID from URL
$bolaoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

// Decode JSON data
$jogos = json_decode($bolao['jogos'], true) ?: [];
$campeonatos = json_decode($bolao['campeonatos'], true) ?: [];

// Sort games by date
usort($jogos, function($a, $b) {
    $dateA = isset($a['data_iso']) ? $a['data_iso'] : $a['data'];
    $dateB = isset($b['data_iso']) ? $b['data_iso'] : $b['data'];
    return strtotime($dateA) - strtotime($dateB);
});

// Form submission
$errors = [];
$formData = $bolao;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form fields
    $formData = [
        'nome' => $_POST['nome'] ?? '',
        'descricao' => $_POST['descricao'] ?? '',
        'data_inicio' => $_POST['data_inicio'] ?? '',
        'data_fim' => $_POST['data_fim'] ?? '',
        'status' => isset($_POST['status']) ? 1 : 0,
        'valor_participacao' => $_POST['valor_participacao'] ?? 0,
        'premio_total' => $_POST['premio_total'] ?? 0,
        'max_participantes' => empty($_POST['max_participantes']) ? null : (int)$_POST['max_participantes'],
        'publico' => isset($_POST['publico']) ? 1 : 0,
        'data_limite_palpitar' => $_POST['data_limite_palpitar'] ?? null
    ];
    
    // Handle image upload
    if (isset($_FILES['imagem_bolao']) && $_FILES['imagem_bolao']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/boloes/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileInfo = pathinfo($_FILES['imagem_bolao']['name']);
        $extension = strtolower($fileInfo['extension']);
        
        // Validate file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = 'Tipo de arquivo não permitido. Use apenas JPG, PNG ou WebP.';
        } else {
            $newFileName = 'bolao_' . $bolaoId . '_' . time() . '.' . $extension;
            $uploadFile = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['imagem_bolao']['tmp_name'], $uploadFile)) {
                // Delete old image if exists
                if (!empty($bolao['imagem_bolao_url'])) {
                    @unlink('../' . $bolao['imagem_bolao_url']);
                }
                $formData['imagem_bolao_url'] = 'uploads/boloes/' . $newFileName;
            } else {
                $errors[] = 'Erro ao fazer upload da imagem.';
            }
        }
    }
    
    // Validate required fields
    if (empty($formData['nome'])) {
        $errors[] = 'O nome do bolão é obrigatório.';
    }
    
    if (empty($formData['data_inicio'])) {
        $errors[] = 'A data de início é obrigatória.';
    }
    
    if (empty($formData['data_fim'])) {
        $errors[] = 'A data de fim é obrigatória.';
    }
    
    // Validate dates
    $dataInicio = new DateTime($formData['data_inicio']);
    $dataFim = new DateTime($formData['data_fim']);
    
    if ($dataFim < $dataInicio) {
        $errors[] = 'A data de fim deve ser posterior à data de início.';
    }
    
    // Validate numeric fields
    if (!is_numeric($formData['valor_participacao']) || $formData['valor_participacao'] < 0) {
        $errors[] = 'O valor de participação deve ser um número positivo.';
    }
    
    if (!is_numeric($formData['premio_total']) || $formData['premio_total'] < 0) {
        $errors[] = 'O prêmio total deve ser um número positivo.';
    }
    
    if ($formData['max_participantes'] !== null && $formData['max_participantes'] <= 0) {
        $errors[] = 'O número máximo de participantes deve ser positivo.';
    }
    
    // If no errors, update the bolão
    if (empty($errors)) {
        // Check if name has changed, if so, update slug
        if ($formData['nome'] !== $bolao['nome']) {
            $slug = slugify($formData['nome']);
            
            // Check for duplicate slug
            $existingSlug = dbFetchOne("SELECT id FROM dados_boloes WHERE slug = ? AND id != ?", [$slug, $bolaoId]);
            if ($existingSlug) {
                $slug = $slug . '-' . time();
            }
            
            $formData['slug'] = $slug;
        }
        
        // Update bolão
        $result = dbUpdate('dados_boloes', $formData, 'id = ?', [$bolaoId]);
        
        if ($result) {
            setFlashMessage('success', 'Bolão atualizado com sucesso!');
            redirect(APP_URL . '/admin/bolao.php?id=' . $bolaoId);
        } else {
            setFlashMessage('danger', 'Erro ao atualizar o bolão.');
        }
    }
}

// Page title
$pageTitle = 'Editar Bolão: ' . $bolao['nome'];

// Include admin header
include '../templates/admin/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $pageTitle ?></h1>
    <div>
        <a href="<?= APP_URL ?>/admin/bolao.php?id=<?= $bolaoId ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= $error ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Coluna da Esquerda - Formulário -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-pencil"></i> Editar Informações
            </div>
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome do Bolão *</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?= sanitize($formData['nome']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="status" name="status" <?= $formData['status'] == 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status">Ativo</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="imagem_bolao" class="form-label">Imagem do Bolão</label>
                        <?php if (!empty($formData['imagem_bolao_url'])): ?>
                            <div class="mb-2">
                                <img src="<?= APP_URL ?>/<?= $formData['imagem_bolao_url'] ?>" 
                                     alt="Imagem atual do bolão" 
                                     class="img-thumbnail" 
                                     style="max-height: 200px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="imagem_bolao" name="imagem_bolao" accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted">Formatos aceitos: JPG, PNG, WebP. A imagem será usada no slider da página inicial.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data_inicio" class="form-label">Data de Início *</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= $formData['data_inicio'] ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="data_fim" class="form-label">Data de Término *</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= $formData['data_fim'] ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="data_limite_palpitar" class="form-label">Data Limite para Palpites</label>
                        <input type="datetime-local" class="form-control" id="data_limite_palpitar" name="data_limite_palpitar" value="<?= $formData['data_limite_palpitar'] ? date('Y-m-d\TH:i', strtotime($formData['data_limite_palpitar'])) : '' ?>">
                        <small class="text-muted">Se não definido, será possível palpitar até o início do primeiro jogo</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="valor_participacao" class="form-label">Valor de Participação (R$)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="valor_participacao" name="valor_participacao" value="<?= $formData['valor_participacao'] ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="premio_total" class="form-label">Prêmio Total (R$)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="premio_total" name="premio_total" value="<?= $formData['premio_total'] ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="max_participantes" class="form-label">Máximo de Participantes</label>
                            <input type="number" min="0" class="form-control" id="max_participantes" name="max_participantes" value="<?= $formData['max_participantes'] ?>" placeholder="Ilimitado se vazio">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="publico" name="publico" <?= $formData['publico'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="publico">
                                    Bolão público (visível para todos)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4"><?= sanitize($formData['descricao']) ?></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Coluna da Direita - Jogos -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-controller"></i> Jogos (<?= count($jogos) ?>)
            </div>
            <div class="card-body">
                <?php if (empty($jogos)): ?>
                    <p class="text-muted">Nenhum jogo selecionado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Campeonato</th>
                                    <th>Partida</th>
                                    <th>Resultado</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jogos as $jogo): ?>
                                    <tr>
                                        <td><?= $jogo['data'] ?></td>
                                        <td><?= htmlspecialchars($jogo['campeonato']) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($jogo['time_casa']) ?></strong>
                                            x 
                                            <strong><?= htmlspecialchars($jogo['time_visitante']) ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($jogo['resultado_casa'] !== null && $jogo['resultado_visitante'] !== null): ?>
                                                <span class="badge bg-green">
                                                    <?= $jogo['resultado_casa'] ?> x <?= $jogo['resultado_visitante'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Aguardando</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $dataJogo = strtotime($jogo['data_iso'] ?? $jogo['data']);
                                            $agora = time();
                                            if ($jogo['resultado_casa'] !== null):
                                                echo '<span class="badge bg-success">Finalizado</span>';
                                            elseif ($dataJogo < $agora):
                                                echo '<span class="badge bg-warning">Em Andamento</span>';
                                            else:
                                                echo '<span class="badge bg-info">Agendado</span>';
                                            endif;
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Campeonatos -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-trophy"></i> Campeonatos
            </div>
            <div class="card-body">
                <?php if (empty($campeonatos)): ?>
                    <p class="text-muted">Nenhum campeonato selecionado.</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($campeonatos as $campeonato): ?>
                            <span class="badge bg-info text-dark">
                                <?= htmlspecialchars($campeonato['nome']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../templates/admin/footer.php';
?> 