<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_admin.php';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar modelo de pagamento
        $modeloPagamento = filter_input(INPUT_POST, 'modelo_pagamento', FILTER_SANITIZE_STRING);
        if (!in_array($modeloPagamento, ['por_aposta', 'conta_saldo'])) {
            throw new Exception('Modelo de pagamento inválido');
        }

        // Atualizar configuração
        $sql = "UPDATE configuracoes SET valor = ? WHERE nome_configuracao = 'modelo_pagamento' AND categoria = 'pagamento'";
        dbExecute($sql, [$modeloPagamento]);

        // Atualizar outras configurações
        $configsNumericas = [
            'deposito_minimo',
            'deposito_maximo',
            'saque_minimo',
            'saque_maximo',
            'taxa_saque',
            'prazo_saque'
        ];

        foreach ($configsNumericas as $config) {
            $valor = filter_input(INPUT_POST, $config, FILTER_VALIDATE_FLOAT);
            if ($valor !== false) {
                dbExecute(
                    "UPDATE configuracoes SET valor = ? WHERE nome_configuracao = ? AND categoria = 'pagamento'", 
                    [$valor, $config]
                );
            }
        }

        // Atualizar métodos aceitos
        $metodosDeposito = isset($_POST['metodos_deposito']) ? $_POST['metodos_deposito'] : [];
        $metodosSaque = isset($_POST['metodos_saque']) ? $_POST['metodos_saque'] : [];

        dbExecute(
            "UPDATE configuracoes SET valor = ? WHERE nome_configuracao = 'metodos_deposito' AND categoria = 'pagamento'",
            [json_encode($metodosDeposito)]
        );
        dbExecute(
            "UPDATE configuracoes SET valor = ? WHERE nome_configuracao = 'metodos_saque' AND categoria = 'pagamento'",
            [json_encode($metodosSaque)]
        );

        setFlashMessage('success', 'Configurações atualizadas com sucesso!');
        redirect($_SERVER['PHP_SELF']);

    } catch (Exception $e) {
        setFlashMessage('danger', 'Erro ao atualizar configurações: ' . $e->getMessage());
    }
}

// Buscar configurações atuais
$sql = "SELECT nome_configuracao, valor FROM configuracoes WHERE categoria = 'pagamento'";
$configsPagamento = dbFetchAll($sql);
$configs = [];
foreach ($configsPagamento as $config) {
    $configs[$config['nome_configuracao']] = $config['valor'];
}

// Decodificar métodos JSON
$metodosDeposito = json_decode($configs['metodos_deposito'] ?? '[]', true);
$metodosSaque = json_decode($configs['metodos_saque'] ?? '[]', true);

$pageTitle = "Configurações de Pagamento";
include '../templates/admin/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Configurações de Pagamento</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <!-- Modelo de Pagamento -->
                        <div class="mb-4">
                            <h6 class="mb-3">Modelo de Pagamento</h6>
                            <div class="form-check mb-2">
                                <input type="radio" class="form-check-input" name="modelo_pagamento" 
                                       id="modelo_por_aposta" value="por_aposta" 
                                       <?= ($configs['modelo_pagamento'] ?? 'por_aposta') === 'por_aposta' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="modelo_por_aposta">
                                    <strong>Pagamento por Aposta</strong>
                                    <div class="text-muted small">Cada aposta requer um pagamento individual</div>
                                </label>
                            </div>
                            <div class="form-check">
                                <input type="radio" class="form-check-input" name="modelo_pagamento" 
                                       id="modelo_conta_saldo" value="conta_saldo"
                                       <?= ($configs['modelo_pagamento'] ?? '') === 'conta_saldo' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="modelo_conta_saldo">
                                    <strong>Conta com Saldo</strong>
                                    <div class="text-muted small">Jogadores mantêm saldo na conta para apostar</div>
                                </label>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Limites de Valores -->
                        <h6 class="mb-3">Limites de Valores</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Depósito Mínimo</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" name="deposito_minimo" 
                                           value="<?= $configs['deposito_minimo'] ?? '10.00' ?>" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Depósito Máximo</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" name="deposito_maximo" 
                                           value="<?= $configs['deposito_maximo'] ?? '5000.00' ?>" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Saque Mínimo</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" name="saque_minimo" 
                                           value="<?= $configs['saque_minimo'] ?? '30.00' ?>" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Saque Máximo</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" name="saque_maximo" 
                                           value="<?= $configs['saque_maximo'] ?? '5000.00' ?>" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Métodos de Pagamento -->
                        <h6 class="mb-3">Métodos de Pagamento Aceitos</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Métodos para Depósito</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="metodos_deposito[]" 
                                           value="pix" id="deposito_pix"
                                           <?= in_array('pix', $metodosDeposito) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="deposito_pix">PIX</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="metodos_deposito[]" 
                                           value="cartao_credito" id="deposito_cartao"
                                           <?= in_array('cartao_credito', $metodosDeposito) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="deposito_cartao">Cartão de Crédito</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Métodos para Saque</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="metodos_saque[]" 
                                           value="pix" id="saque_pix"
                                           <?= in_array('pix', $metodosSaque) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="saque_pix">PIX</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="metodos_saque[]" 
                                           value="transferencia_bancaria" id="saque_transferencia"
                                           <?= in_array('transferencia_bancaria', $metodosSaque) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="saque_transferencia">Transferência Bancária</label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Outras Configurações -->
                        <h6 class="mb-3">Outras Configurações</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Taxa de Saque (%)</label>
                                <input type="number" class="form-control" name="taxa_saque" 
                                       value="<?= $configs['taxa_saque'] ?? '0.00' ?>" 
                                       step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prazo para Saque (dias úteis)</label>
                                <input type="number" class="form-control" name="prazo_saque" 
                                       value="<?= $configs['prazo_saque'] ?? '2' ?>" 
                                       step="1" min="1" required>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Salvar Configurações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validação do formulário
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php include '../templates/admin/footer.php'; ?> 