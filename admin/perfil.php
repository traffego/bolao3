<?php
/**
 * Admin Perfil - Bolão Football
 * Gerenciamento do perfil do administrador
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/perfil.php';
    redirect(APP_URL . '/admin/login.php');
}

// Buscar dados do admin
$admin_id = $_SESSION['admin_id'];
$admin = dbFetchOne(
    "SELECT * FROM administrador WHERE id = ?", 
    [$admin_id]
);

if (!$admin) {
    setError('Administrador não encontrado');
    redirect(APP_URL . '/admin/logout.php');
}

// Buscar últimas ações do admin
$ultimas_acoes = dbFetchAll(
    "SELECT * FROM logs 
     WHERE usuario_id = ? 
     ORDER BY data_hora DESC 
     LIMIT 10",
    [$admin_id]
);

// Incluir header do admin
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <!-- Hero Section -->
    <div class="hero-section bg-dark text-white py-4 mb-4" style="border-radius: 24px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="hero-icon me-4">
                            <i class="fas fa-user-shield fa-4x"></i>
                        </div>
                        <div>
                            <h1 class="display-4 mb-2">Meu Perfil</h1>
                            <p class="lead mb-0">Gerencie suas informações de administrador</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-end">
                        <a href="<?= APP_URL ?>/admin/" class="btn btn-light btn-lg">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Informações do Perfil -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-green">
                    <h5 class="mb-0">Informações do Perfil</h5>
                </div>
                <div class="card-body">
                    <form action="<?= APP_URL ?>/admin/acao-perfil.php" method="post" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nome" 
                                   name="nome" 
                                   value="<?= htmlspecialchars($admin['nome']) ?>"
                                   required>
                            <div class="invalid-feedback">
                                Por favor, informe seu nome.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($admin['email']) ?>"
                                   required>
                            <div class="invalid-feedback">
                                Por favor, informe um email válido.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="telefone" 
                                   name="telefone"
                                   value="<?= htmlspecialchars($admin['telefone'] ?? '') ?>">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Alterar Senha -->
            <div class="card mb-4">
                <div class="card-header bg-green">
                    <h5 class="mb-0">Alterar Senha</h5>
                </div>
                <div class="card-body">
                    <form action="<?= APP_URL ?>/admin/acao-perfil.php" method="post" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="mb-3">
                            <label for="senha_atual" class="form-label">Senha Atual *</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="senha_atual" 
                                   name="senha_atual" 
                                   required>
                            <div class="invalid-feedback">
                                Por favor, informe sua senha atual.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="nova_senha" class="form-label">Nova Senha *</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="nova_senha" 
                                   name="nova_senha" 
                                   required
                                   minlength="8">
                            <div class="invalid-feedback">
                                A senha deve ter no mínimo 8 caracteres.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirmar_senha" class="form-label">Confirmar Nova Senha *</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirmar_senha" 
                                   name="confirmar_senha" 
                                   required>
                            <div class="invalid-feedback">
                                As senhas não conferem.
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>
                                Alterar Senha
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Últimas Ações -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-green">
                    <h5 class="mb-0">Últimas Ações</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Ação</th>
                                    <th>Descrição</th>
                                    <th>Data/Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimas_acoes as $acao): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= getAcaoBadgeClass($acao['tipo']) ?>">
                                                <?= ucfirst($acao['tipo']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($acao['descricao']) ?></td>
                                        <td><?= formatDate($acao['data_hora'], true) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($ultimas_acoes)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Nenhuma ação registrada
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Informações da Conta -->
            <div class="card mb-4">
                <div class="card-header bg-green">
                    <h5 class="mb-0">Informações da Conta</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-calendar me-2"></i>
                                Data de Cadastro
                            </div>
                            <span><?= formatDate($admin['data_cadastro']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-clock me-2"></i>
                                Último Login
                            </div>
                            <span><?= formatDate($admin['ultimo_login'], true) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-shield-alt me-2"></i>
                                Status da Conta
                            </div>
                            <span class="badge bg-<?= $admin['status'] === 'ativo' ? 'success' : 'danger' ?>">
                                <?= ucfirst($admin['status']) ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-list me-2"></i>
                                Total de Ações
                            </div>
                            <span class="badge bg-primary rounded-pill">
                                <?= dbCount('logs', 'usuario_id = ?', [$admin_id]) ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts específicos da página -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validação dos formulários
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Validação adicional para o formulário de senha
            if (form.querySelector('#nova_senha')) {
                const novaSenha = form.querySelector('#nova_senha').value;
                const confirmarSenha = form.querySelector('#confirmar_senha').value;
                
                if (novaSenha !== confirmarSenha) {
                    event.preventDefault();
                    form.querySelector('#confirmar_senha').setCustomValidity('As senhas não conferem');
                } else {
                    form.querySelector('#confirmar_senha').setCustomValidity('');
                }
            }
            
            form.classList.add('was-validated');
        });
    });
    
    // Máscara para telefone
    const telefone = document.getElementById('telefone');
    if (telefone) {
        telefone.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = '(' + value;
                if (value.length > 3) {
                    value = value.substring(0, 3) + ') ' + value.substring(3);
                }
                if (value.length > 10) {
                    value = value.substring(0, 10) + '-' + value.substring(10);
                }
                if (value.length > 15) {
                    value = value.substring(0, 15);
                }
            }
            e.target.value = value;
        });
    }
});

// Função auxiliar para definir a classe do badge da ação
function getAcaoBadgeClass(tipo) {
    const classes = {
        'criar': 'success',
        'atualizar': 'info',
        'excluir': 'danger',
        'login': 'primary',
        'logout': 'secondary'
    };
    
    return classes[tipo.toLowerCase()] || 'primary';
}
</script>

<?php include '../templates/admin/footer.php'; ?> 