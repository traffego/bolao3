<?php
/**
 * Admin Regras do Bolão - Bolão Football
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/regras-bolao.php';
    redirect(APP_URL . '/admin/login.php');
}

// Get all rules
$regras = dbFetchAll("SELECT * FROM regras_bolao ORDER BY id ASC");

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pontos_acerto_exato = (int)$_POST['pontos_acerto_exato'];
    $pontos_acerto_vencedor = (int)$_POST['pontos_acerto_vencedor'];
    $pontos_acerto_placar_time1 = (int)$_POST['pontos_acerto_placar_time1'];
    $pontos_acerto_placar_time2 = (int)$_POST['pontos_acerto_placar_time2'];
    $pontos_erro_total = (int)$_POST['pontos_erro_total'];
    
    // Validate points
    $errors = [];
    if ($pontos_acerto_exato < 0) {
        $errors[] = 'Os pontos por acerto exato devem ser positivos.';
    }
    if ($pontos_acerto_vencedor < 0) {
        $errors[] = 'Os pontos por acerto do vencedor devem ser positivos.';
    }
    if ($pontos_acerto_placar_time1 < 0) {
        $errors[] = 'Os pontos por acerto do placar do time 1 devem ser positivos.';
    }
    if ($pontos_acerto_placar_time2 < 0) {
        $errors[] = 'Os pontos por acerto do placar do time 2 devem ser positivos.';
    }
    
    if (empty($errors)) {
        // Update or insert rules
        $data = [
            'pontos_acerto_exato' => $pontos_acerto_exato,
            'pontos_acerto_vencedor' => $pontos_acerto_vencedor,
            'pontos_acerto_placar_time1' => $pontos_acerto_placar_time1,
            'pontos_acerto_placar_time2' => $pontos_acerto_placar_time2,
            'pontos_erro_total' => $pontos_erro_total
        ];
        
        if (empty($regras)) {
            $result = dbInsert('regras_bolao', $data);
        } else {
            $result = dbUpdate('regras_bolao', $data, 'id = ?', [$regras[0]['id']]);
        }
        
        if ($result) {
            setFlashMessage('success', 'Regras atualizadas com sucesso!');
            redirect(APP_URL . '/admin/regras-bolao.php');
        } else {
            setFlashMessage('danger', 'Erro ao atualizar as regras.');
        }
    }
}

// Page title
$pageTitle = 'Regras do Bolão';

// Include admin header
include '../templates/admin/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $pageTitle ?></h1>
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

<div class="card">
    <div class="card-body">
        <form action="" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="pontos_acerto_exato" class="form-label">Pontos por Acerto Exato</label>
                    <input type="number" class="form-control" id="pontos_acerto_exato" name="pontos_acerto_exato" 
                           value="<?= !empty($regras) ? $regras[0]['pontos_acerto_exato'] : 10 ?>" required>
                    <small class="text-muted">Pontos quando acerta o placar exato do jogo</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="pontos_acerto_vencedor" class="form-label">Pontos por Acerto do Vencedor</label>
                    <input type="number" class="form-control" id="pontos_acerto_vencedor" name="pontos_acerto_vencedor" 
                           value="<?= !empty($regras) ? $regras[0]['pontos_acerto_vencedor'] : 5 ?>" required>
                    <small class="text-muted">Pontos quando acerta apenas quem venceu ou se foi empate</small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="pontos_acerto_placar_time1" class="form-label">Pontos por Acerto do Placar Time 1</label>
                    <input type="number" class="form-control" id="pontos_acerto_placar_time1" name="pontos_acerto_placar_time1" 
                           value="<?= !empty($regras) ? $regras[0]['pontos_acerto_placar_time1'] : 3 ?>" required>
                    <small class="text-muted">Pontos quando acerta apenas o placar do time 1</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="pontos_acerto_placar_time2" class="form-label">Pontos por Acerto do Placar Time 2</label>
                    <input type="number" class="form-control" id="pontos_acerto_placar_time2" name="pontos_acerto_placar_time2" 
                           value="<?= !empty($regras) ? $regras[0]['pontos_acerto_placar_time2'] : 3 ?>" required>
                    <small class="text-muted">Pontos quando acerta apenas o placar do time 2</small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="pontos_erro_total" class="form-label">Pontos por Erro Total</label>
                    <input type="number" class="form-control" id="pontos_erro_total" name="pontos_erro_total" 
                           value="<?= !empty($regras) ? $regras[0]['pontos_erro_total'] : 0 ?>" required>
                    <small class="text-muted">Pontos quando erra completamente o resultado</small>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary">Salvar Regras</button>
            </div>
        </form>
    </div>
</div>

<?php include '../templates/admin/footer.php'; ?> 