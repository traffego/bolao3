<?php
/**
 * Admin Novo Afiliado - Bolão Football
 * Formulário para criação de novos afiliados
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/novo-afiliado.php';
    redirect(APP_URL . '/admin/login.php');
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
                            <i class="fas fa-user-plus fa-4x"></i>
                        </div>
                        <div>
                            <h1 class="display-4 mb-2">Novo Afiliado</h1>
                            <p class="lead mb-0">Cadastre um novo afiliado no sistema</p>
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
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="row g-3">
                    <!-- Nome -->
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" 
                               class="form-control" 
                               id="nome" 
                               name="nome" 
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
                               name="telefone">
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
                               value="10" 
                               required>
                        <div class="invalid-feedback">
                            Por favor, informe um percentual de comissão válido (0-100).
                        </div>
                    </div>

                    <!-- Chave PIX -->
                    <div class="col-md-6">
                        <label for="pix_chave" class="form-label">Chave PIX</label>
                        <input type="text" 
                               class="form-control" 
                               id="pix_chave" 
                               name="pix_chave">
                    </div>

                    <!-- Tipo PIX -->
                    <div class="col-md-6">
                        <label for="pix_tipo" class="form-label">Tipo da Chave PIX</label>
                        <select class="form-select" id="pix_tipo" name="pix_tipo">
                            <option value="">Selecione...</option>
                            <option value="cpf">CPF</option>
                            <option value="cnpj">CNPJ</option>
                            <option value="email">Email</option>
                            <option value="telefone">Telefone</option>
                            <option value="aleatoria">Chave Aleatória</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                        <div class="invalid-feedback">
                            Por favor, selecione o status.
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= APP_URL ?>/admin/afiliados.php" class="btn btn-secondary">
                        Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        Salvar Afiliado
                    </button>
                </div>
            </form>
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