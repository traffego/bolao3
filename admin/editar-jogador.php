<?php
/**
 * Editar Jogador - Bolão Football
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

// Verificar ID do jogador
$jogador_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$jogador_id) {
    $_SESSION['error'] = 'ID do jogador inválido.';
    redirect(APP_URL . '/admin/jogadores.php');
}

// Buscar dados do jogador com saldo
$jogador = dbFetchOne("
    SELECT 
        j.*,
        COALESCE((
            SELECT SUM(
                CASE 
                    WHEN t.tipo IN ('deposito', 'premio', 'bonus') AND t.status = 'aprovado' THEN t.valor
                    WHEN t.tipo IN ('saque', 'aposta') AND t.status IN ('aprovado', 'pendente') THEN -t.valor
                    ELSE 0
                END
            )
            FROM transacoes t 
            INNER JOIN contas c ON t.conta_id = c.id 
            WHERE c.jogador_id = j.id
        ), 0) as saldo
    FROM jogador j 
    WHERE j.id = ?
", [$jogador_id]);
if (!$jogador) {
    $_SESSION['error'] = 'Jogador não encontrado.';
    redirect(APP_URL . '/admin/jogadores.php');
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Token de segurança inválido.';
        redirect(APP_URL . '/admin/editar-jogador.php?id=' . $jogador_id);
    }

    // Coletar e validar dados
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $status = $_POST['status'] ?? 'ativo';
    $senha = trim($_POST['senha'] ?? '');
    $comissao_afiliado = isset($_POST['comissao_afiliado']) ? (float)$_POST['comissao_afiliado'] : 10.00;

    $errors = [];

    // Validações
    if (empty($nome)) {
        $errors[] = 'Nome é obrigatório.';
    }

    if (empty($email)) {
        $errors[] = 'Email é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email inválido.';
    } else {
        // Verificar se email já existe para outro jogador
        $emailExists = dbFetchOne(
            "SELECT id FROM jogador WHERE email = ? AND id != ?",
            [$email, $jogador_id]
        );
        if ($emailExists) {
            $errors[] = 'Este email já está em uso por outro jogador.';
        }
    }

    if (!empty($cpf)) {
        // Remover caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Verificar se CPF já existe para outro jogador
        $cpfExists = dbFetchOne(
            "SELECT id FROM jogador WHERE cpf = ? AND id != ?",
            [$cpf, $jogador_id]
        );
        if ($cpfExists) {
            $errors[] = 'Este CPF já está cadastrado para outro jogador.';
        }
    }

    if (!empty($senha)) {
        if (strlen($senha) < 6) {
            $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
        }
    }

    if (!in_array($status, ['ativo', 'inativo'])) {
        $errors[] = 'Status inválido.';
    }

    // Validar comissão do afiliado
    if ($comissao_afiliado < 0 || $comissao_afiliado > 50) {
        $errors[] = 'A comissão do afiliado deve estar entre 0% e 50%.';
    }

    // Se não houver erros, atualizar jogador
    if (empty($errors)) {
        $query = "UPDATE jogador SET 
                    nome = ?,
                    email = ?,
                    telefone = ?,
                    cpf = ?,
                    status = ?,
                    comissao_afiliado = ?";
        
        $params = [
            $nome,
            $email,
            $telefone,
            $cpf ?: null,
            $status,
            $comissao_afiliado
        ];

        // Se senha foi fornecida, atualizar
        if (!empty($senha)) {
            $query .= ", senha = ?";
            $params[] = password_hash($senha, PASSWORD_DEFAULT);
        }

        $query .= " WHERE id = ?";
        $params[] = $jogador_id;

        $success = dbExecute($query, $params);

        if ($success) {
            // Registrar ação no log
            logAdminAction(
                'jogador',
                "Editou dados do jogador {$nome}",
                [
                    'jogador_id' => $jogador_id,
                    'dados_alterados' => [
                        'nome' => $nome,
                        'email' => $email,
                        'telefone' => $telefone,
                        'cpf' => $cpf,
                        'status' => $status,
                        'comissao_afiliado' => $comissao_afiliado
                    ]
                ]
            );

            $_SESSION['success'] = 'Jogador atualizado com sucesso.';
            redirect(APP_URL . '/admin/jogadores.php');
        } else {
            $errors[] = 'Erro ao atualizar jogador.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Incluir header do admin
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Editar Jogador</h1>
        <div>
            <a href="<?= APP_URL ?>/admin/jogadores.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="" method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?= htmlspecialchars($jogador['nome']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($jogador['email']) ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="tel" class="form-control" id="telefone" name="telefone" 
                               value="<?= htmlspecialchars($jogador['telefone'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" 
                               value="<?= htmlspecialchars($jogador['cpf'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="senha" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" 
                               minlength="6">
                        <div class="form-text">Deixe em branco para manter a senha atual.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="ativo" <?= $jogador['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= $jogador['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="comissao_afiliado" class="form-label">Comissão do Afiliado (%)</label>
                        <input type="number" class="form-control" id="comissao_afiliado" name="comissao_afiliado" 
                               value="<?= htmlspecialchars($jogador['comissao_afiliado'] ?? '10.00') ?>" 
                               min="0" max="50" step="0.01" required>
                        <div class="form-text">Percentual de comissão que este jogador receberá por indicações (0% a 50%).</div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <strong>Informações do Jogador:</strong><br>
                            Data de Cadastro: <?= formatDate($jogador['data_cadastro']) ?><br>
                            Último Acesso: <?= $jogador['ultimo_acesso'] ? formatDate($jogador['ultimo_acesso']) : 'Nunca' ?><br>
                            Saldo: <?= formatMoney($jogador['saldo']) ?>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para telefone
    const telefoneInput = document.getElementById('telefone');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            
            if (value.length > 2) {
                value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
            }
            if (value.length > 9) {
                value = `${value.slice(0, 9)}-${value.slice(9)}`;
            }
            
            e.target.value = value;
        });
    }

    // Máscara para CPF
    const cpfInput = document.getElementById('cpf');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            
            if (value.length > 3) {
                value = `${value.slice(0, 3)}.${value.slice(3)}`;
            }
            if (value.length > 7) {
                value = `${value.slice(0, 7)}.${value.slice(7)}`;
            }
            if (value.length > 11) {
                value = `${value.slice(0, 11)}-${value.slice(11)}`;
            }
            
            e.target.value = value;
        });
    }

    // Validação do formulário
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }
});
</script>

<?php include '../templates/admin/footer.php'; ?>