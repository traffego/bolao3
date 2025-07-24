<?php
/**
 * Admin Editar Afiliado - Bolão Football
 * Formulário para edição de afiliados existentes
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/editar-afiliado.php';
    redirect(APP_URL . '/admin/login.php');
}

// Verificar ID do afiliado
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setError('ID do afiliado inválido');
    redirect(APP_URL . '/admin/afiliados.php');
}

// Buscar dados do afiliado
$afiliado = dbFetchOne("SELECT * FROM afiliados WHERE id = ?", [$id]);
if (!$afiliado) {
    setError('Afiliado não encontrado');
    redirect(APP_URL . '/admin/afiliados.php');
}

// Incluir header do admin
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <!-- Hero Section -->
    <div class="hero-section bg-primary text-white py-4 mb-4" style="border-radius: 24px;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="hero-icon me-4">
                            <i class="fas fa-user-edit fa-4x"></i>
                        </div>
                        <div>
                            <h1 class="display-4 mb-2">Editar Afiliado</h1>
                            <p class="lead mb-0">Editar dados do afiliado <?= htmlspecialchars($afiliado['nome']) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-end">
                        <a href="<?= APP_URL ?>/admin/afiliados.php" class="btn btn-light btn-lg">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulário -->
    <div class="card mb-4">
        <div class="card-header bg-green">
            <h5 class="mb-0">Dados do Afiliado</h5>
        </div>
        <div class="card-body">
            <form action="<?= APP_URL ?>/admin/acao-afiliado.php" method="post" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="row g-3">
                    <!-- Nome -->
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" 
                               class="form-control" 
                               id="nome" 
                               name="nome" 
                               value="<?= htmlspecialchars($afiliado['nome']) ?>"
                               required>
                        <div class="invalid-feedback">
                            Por favor, informe o nome do afiliado.
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?= htmlspecialchars($afiliado['email']) ?>"
                               required>
                        <div class="invalid-feedback">
                            Por favor, informe um email válido.
                        </div>
                    </div>

                    <!-- Telefone -->
                    <div class="col-md-6">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="tel" 
                               class="form-control" 
                               id="telefone" 
                               name="telefone"
                               value="<?= htmlspecialchars($afiliado['telefone'] ?? '') ?>">
                    </div>

                    <!-- Código do Afiliado -->
                    <div class="col-md-6">
                        <label for="codigo_afiliado" class="form-label">Código do Afiliado</label>
                        <input type="text" 
                               class="form-control" 
                               id="codigo_afiliado" 
                               value="<?= htmlspecialchars($afiliado['codigo_afiliado']) ?>"
                               readonly>
                        <small class="text-muted">O código do afiliado não pode ser alterado</small>
                    </div>

                    <!-- Comissão -->
                    <div class="col-md-6">
                        <label for="comissao_percentual" class="form-label">Comissão (%) *</label>
                        <input type="number" 
                               class="form-control" 
                               id="comissao_percentual" 
                               name="comissao_percentual" 
                               min="0" 
                               max="100" 
                               step="0.1" 
                               value="<?= number_format($afiliado['comissao_percentual'], 2) ?>" 
                               required>
                        <div class="invalid-feedback">
                            Por favor, informe um percentual de comissão válido (0-100).
                        </div>
                    </div>

                    <!-- Saldo -->
                    <div class="col-md-6">
                        <label for="saldo" class="form-label">Saldo Atual</label>
                        <input type="text" 
                               class="form-control" 
                               id="saldo" 
                               value="R$ <?= number_format($afiliado['saldo'], 2, ',', '.') ?>"
                               readonly>
                        <small class="text-muted">O saldo é atualizado automaticamente</small>
                    </div>

                    <!-- Chave PIX -->
                    <div class="col-md-6">
                        <label for="pix_chave" class="form-label">Chave PIX</label>
                        <input type="text" 
                               class="form-control" 
                               id="pix_chave" 
                               name="pix_chave"
                               value="<?= htmlspecialchars($afiliado['pix_chave'] ?? '') ?>">
                    </div>

                    <!-- Tipo PIX -->
                    <div class="col-md-6">
                        <label for="pix_tipo" class="form-label">Tipo da Chave PIX</label>
                        <select class="form-select" id="pix_tipo" name="pix_tipo">
                            <option value="">Selecione...</option>
                            <option value="cpf" <?= $afiliado['pix_tipo'] === 'cpf' ? 'selected' : '' ?>>CPF</option>
                            <option value="cnpj" <?= $afiliado['pix_tipo'] === 'cnpj' ? 'selected' : '' ?>>CNPJ</option>
                            <option value="email" <?= $afiliado['pix_tipo'] === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="telefone" <?= $afiliado['pix_tipo'] === 'telefone' ? 'selected' : '' ?>>Telefone</option>
                            <option value="aleatoria" <?= $afiliado['pix_tipo'] === 'aleatoria' ? 'selected' : '' ?>>Chave Aleatória</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="ativo" <?= $afiliado['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= $afiliado['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                        <div class="invalid-feedback">
                            Por favor, selecione o status.
                        </div>
                    </div>

                    <!-- Data de Cadastro -->
                    <div class="col-md-6">
                        <label for="data_cadastro" class="form-label">Data de Cadastro</label>
                        <input type="text" 
                               class="form-control" 
                               id="data_cadastro" 
                               value="<?= formatDate($afiliado['data_cadastro']) ?>"
                               readonly>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= APP_URL ?>/admin/afiliados.php" class="btn btn-secondary">
                        Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Estatísticas do Afiliado -->
    <div class="row">
        <!-- Indicações -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-green d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Últimas Indicações</h5>
                    <a href="<?= APP_URL ?>/admin/indicacoes-afiliado.php?id=<?= $id ?>" class="btn btn-sm btn-primary">
                        Ver Todas
                    </a>
                </div>
                <div class="card-body">
                    <?php
                    $indicacoes = dbFetchAll(
                        "SELECT i.*, j.nome as jogador_nome, j.email as jogador_email 
                         FROM afiliados_indicacoes i 
                         JOIN jogador j ON i.jogador_id = j.id 
                         WHERE i.afiliado_id = ? 
                         ORDER BY i.data_indicacao DESC 
                         LIMIT 5",
                        [$id]
                    );
                    ?>
                    <?php if ($indicacoes): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Jogador</th>
                                        <th>Email</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($indicacoes as $indicacao): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($indicacao['jogador_nome']) ?></td>
                                            <td><?= htmlspecialchars($indicacao['jogador_email']) ?></td>
                                            <td><?= formatDate($indicacao['data_indicacao']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">Nenhuma indicação encontrada</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Comissões -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-green d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Últimas Comissões</h5>
                    <a href="<?= APP_URL ?>/admin/comissoes-afiliado.php?id=<?= $id ?>" class="btn btn-sm btn-primary">
                        Ver Todas
                    </a>
                </div>
                <div class="card-body">
                    <?php
                    $comissoes = dbFetchAll(
                        "SELECT c.*, j.nome as jogador_nome 
                         FROM afiliados_comissoes c 
                         JOIN jogador j ON c.jogador_id = j.id 
                         WHERE c.afiliado_id = ? 
                         ORDER BY c.data_criacao DESC 
                         LIMIT 5",
                        [$id]
                    );
                    ?>
                    <?php if ($comissoes): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Jogador</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comissoes as $comissao): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($comissao['jogador_nome']) ?></td>
                                            <td>R$ <?= number_format($comissao['valor_comissao'], 2, ',', '.') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $comissao['status'] === 'pago' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($comissao['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= formatDate($comissao['data_criacao']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">Nenhuma comissão encontrada</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts específicos da página -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validação do formulário
    const form = document.querySelector('.needs-validation');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        form.classList.add('was-validated');
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
</script>

<?php include '../templates/admin/footer.php'; ?> 