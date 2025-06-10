<?php
/**
 * Perfil do Usuário - Bolão Vitimba
 */
require_once 'config/config.php';require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/perfil.php';
    redirect(APP_URL . '/login.php');
}

$userId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

// Get user data
$userData = dbFetchOne(
    "SELECT * FROM jogador WHERE id = ?",
    [$userId]
);

// Get user's predictions
$palpites = dbFetchAll(
    "SELECT p.*, b.nome as nome_bolao, b.valor_participacao 
     FROM palpites p 
     JOIN dados_boloes b ON b.id = p.bolao_id 
     WHERE p.jogador_id = ? 
     ORDER BY p.data_palpite DESC",
    [$userId]
);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    // Validate required fields
    if (empty($nome) || empty($email)) {
        $errorMessage = 'Nome e email são campos obrigatórios.';
    } else {
        $updates = [
            'nome' => $nome,
            'telefone' => $telefone
        ];

        // Check if email is being changed
        if ($email !== $userData['email']) {
            // Check if new email is already in use
            $existingUser = dbFetchOne(
                "SELECT id FROM jogador WHERE email = ? AND id != ?",
                [$email, $userId]
            );
            if ($existingUser) {
                $errorMessage = 'Este email já está em uso.';
            } else {
                $updates['email'] = $email;
            }
        }

        // Handle password change
        if (!empty($senha_atual) || !empty($nova_senha) || !empty($confirmar_senha)) {
            if (empty($senha_atual)) {
                $errorMessage = 'A senha atual é necessária para alterar a senha.';
            } elseif (empty($nova_senha) || empty($confirmar_senha)) {
                $errorMessage = 'Nova senha e confirmação são necessárias.';
            } elseif ($nova_senha !== $confirmar_senha) {
                $errorMessage = 'A nova senha e a confirmação não coincidem.';
            } elseif (!password_verify($senha_atual, $userData['senha'])) {
                $errorMessage = 'Senha atual incorreta.';
            } else {
                $updates['senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);
            }
        }

        // If no errors, update the profile
        if (empty($errorMessage)) {
            $setClauses = [];
            $params = [];
            foreach ($updates as $field => $value) {
                $setClauses[] = "$field = ?";
                $params[] = $value;
            }
            $params[] = $userId;

            $updateQuery = "UPDATE jogador SET " . implode(', ', $setClauses) . " WHERE id = ?";
            
            if (dbQuery($updateQuery, $params)) {
                $successMessage = 'Perfil atualizado com sucesso!';
                // Refresh user data
                $userData = dbFetchOne(
                    "SELECT * FROM jogador WHERE id = ?",
                    [$userId]
                );
            } else {
                $errorMessage = 'Erro ao atualizar o perfil. Tente novamente.';
            }
        }
    }
}

// Page title
$pageTitle = 'Meu Perfil';

// Include header
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h1 class="h4 mb-0"><?= $pageTitle ?></h1>
                </div>
                <div class="card-body">
                    <?php if ($successMessage): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
                    <?php endif; ?>

                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   value="<?= htmlspecialchars($userData['nome']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($userData['email']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="tel" class="form-control" id="telefone" name="telefone" 
                                   value="<?= htmlspecialchars($userData['telefone'] ?? '') ?>">
                        </div>

                        <hr class="my-4">

                        <h5>Alterar Senha</h5>
                        <p class="text-muted small">Preencha apenas se desejar alterar sua senha</p>

                        <div class="mb-3">
                            <label for="senha_atual" class="form-label">Senha Atual</label>
                            <input type="password" class="form-control" id="senha_atual" name="senha_atual">
                        </div>

                        <div class="mb-3">
                            <label for="nova_senha" class="form-label">Nova Senha</label>
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha">
                        </div>

                        <div class="mb-3">
                            <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informações da Conta</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Status da Conta</dt>
                        <dd class="col-sm-8">
                            <?php if ($userData['status'] === 'ativo'): ?>
                                <span class="badge bg-success">Ativo</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inativo</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Data de Cadastro</dt>
                        <dd class="col-sm-8"><?= formatDateTime($userData['data_cadastro']) ?></dd>

                        <dt class="col-sm-4">Último Acesso</dt>
                        <dd class="col-sm-8">
                            <?= $userData['ultimo_acesso'] ? formatDateTime($userData['ultimo_acesso']) : 'Nunca' ?>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Meus Palpites</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($palpites)): ?>
                        <p class="text-muted mb-0">Você ainda não fez nenhum palpite.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Bolão</th>
                                        <th>Data do Palpite</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($palpites as $palpite): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($palpite['nome_bolao']) ?></td>
                                            <td><?= formatDateTime($palpite['data_palpite']) ?></td>
                                            <td><?= formatMoney($palpite['valor_participacao']) ?></td>
                                            <td>
                                                <?php if ($palpite['status'] ?? false): ?>
                                                    <span class="badge bg-success">Pago</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pendente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="meus-palpites.php?bolao_id=<?= $palpite['bolao_id'] ?>" 
                                                   class="btn btn-sm btn-primary">Ver Palpites</a>
                                                <?php if (!($palpite['status'] ?? false)): ?>
                                                    <a href="pagamento.php?bolao_id=<?= $palpite['bolao_id'] ?>" 
                                                       class="btn btn-sm btn-success">Pagar</a>
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