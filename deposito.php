<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/classes/NotificacaoManager.php';

// Verifica se está logado
if (!isLoggedIn()) {
    setFlashMessage('warning', 'Você precisa estar logado para fazer um depósito.');
    redirect(APP_URL . '/login.php');
}

// Verifica se é uma retomada de pagamento
$transacaoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$transacao = null;

if ($transacaoId) {
    // Busca a transação
    $sql = "
        SELECT t.*, c.jogador_id 
        FROM transacoes t
        INNER JOIN contas c ON t.conta_id = c.id
        WHERE t.id = ? AND t.tipo = 'deposito' AND t.status = 'pendente'";
    $transacao = dbFetchOne($sql, [$transacaoId]);

    // Verifica se a transação existe e pertence ao usuário
    if (!$transacao || $transacao['jogador_id'] != getCurrentUserId()) {
        setFlashMessage('warning', 'Transação não encontrada ou já processada.');
        redirect(APP_URL . '/minha-conta.php');
    }
}

// Busca configurações de depósito
$sql = "SELECT nome_configuracao, valor FROM configuracoes 
        WHERE nome_configuracao IN ('deposito_minimo', 'deposito_maximo') 
        AND categoria = 'pagamento'";
$configsPagamento = dbFetchAll($sql);

// Define valores padrão
$configs = [
    'deposito_minimo' => 1.00,  // Valor default conforme configuração atual
    'deposito_maximo' => 5000.00
];

// Atualiza com valores do banco se existirem
foreach ($configsPagamento as $config) {
    if (isset($config['nome_configuracao']) && isset($config['valor'])) {
        // Remove as aspas duplas do valor e converte para float
        $valor = floatval(trim($config['valor'], '"'));
        $configs[$config['nome_configuracao']] = $valor;
    }
}

$pageTitle = "Realizar Depósito";
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- Card Principal -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="card-title mb-4">Realizar Depósito</h4>
                    
                    <!-- Etapa 1: Formulário de Valor -->
                    <div id="etapaValor">
                        <form id="formDeposito" class="needs-validation" novalidate>
                            <?php if ($transacao): ?>
                            <input type="hidden" name="transacao_id" value="<?= $transacao['id'] ?>">
                            <?php endif; ?>
                            <div class="mb-4">
                                <label class="form-label">Valor do Depósito</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" 
                                           class="form-control form-control-lg" 
                                           name="valor" 
                                           id="valorDeposito"
                                           min="<?= number_format($configs['deposito_minimo'], 2, '.', '') ?>" 
                                           max="<?= number_format($configs['deposito_maximo'], 2, '.', '') ?>" 
                                           step="0.01" 
                                           value="<?= $transacao ? number_format($transacao['valor'], 2, '.', '') : '' ?>"
                                           <?= $transacao ? 'readonly' : '' ?>
                                           required>
                                </div>
                                <div class="form-text">
                                    <?php if ($transacao): ?>
                                        <i class="fas fa-info-circle"></i> Retomando pagamento pendente
                                    <?php else: ?>
                                        Mínimo: R$ <?= number_format($configs['deposito_minimo'], 2, ',', '.') ?> | 
                                        Máximo: R$ <?= number_format($configs['deposito_maximo'], 2, ',', '.') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <?php if ($transacao): ?>
                                        <i class="fas fa-redo-alt"></i> Retomar Pagamento
                                    <?php else: ?>
                                        <i class="fas fa-plus-circle"></i> Gerar PIX
                                    <?php endif; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Etapa 2: QR Code e Instruções -->
                    <div id="etapaPix" style="display: none;">
                        <div class="text-center mb-4">
                            <div class="qr-code-container mb-3">
                                <img id="qrCodeImage" src="" alt="QR Code PIX" class="img-fluid">
                            </div>
                            
                            <div class="mb-4">
                                <h5>Valor a Pagar</h5>
                                <h3 class="text-success" id="valorPagar"></h3>
                            </div>
                            
                            <div class="mb-4">
                                <button class="btn btn-outline-primary mb-2" id="btnCopiarPix">
                                    <i class="fas fa-copy"></i> Copiar Código PIX
                                </button>
                                <div class="form-text">Cole o código no seu aplicativo do banco</div>
                            </div>
                            
                            <div class="mb-4">
                                <button class="btn btn-success mb-2" id="btnJaPaguei">
                                    <i class="fas fa-check"></i> Já realizei o pagamento
                                </button>
                                <div class="form-text">Clique aqui após realizar o pagamento para verificarmos</div>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6 class="alert-heading">Instruções:</h6>
                                <ol class="text-start mb-0">
                                    <li>Abra o aplicativo do seu banco</li>
                                    <li>Escolha a opção PIX</li>
                                    <li>Escaneie o QR Code ou cole o código copiado</li>
                                    <li>Confirme as informações e valor</li>
                                    <li>Conclua o pagamento</li>
                                </ol>
                            </div>
                        </div>
                        
                        <!-- Status do Pagamento -->
                        <div id="statusPagamento" class="alert alert-warning">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border spinner-border-sm me-2" role="status">
                                    <span class="visually-hidden">Aguardando...</span>
                                </div>
                                <div>Aguardando confirmação do pagamento...</div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="btnNovoDeposito">
                                Fazer Outro Depósito
                            </button>
                            <a href="minha-conta.php" class="btn btn-link">Voltar para Minha Conta</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const formDeposito = document.getElementById('formDeposito');
    const etapaValor = document.getElementById('etapaValor');
    const etapaPix = document.getElementById('etapaPix');
    const btnCopiarPix = document.getElementById('btnCopiarPix');
    const btnNovoDeposito = document.getElementById('btnNovoDeposito');
    const btnJaPaguei = document.getElementById('btnJaPaguei');
    const statusPagamento = document.getElementById('statusPagamento');
    let intervalId = null;
    let transacaoId = null;
    let verificacaoManualEmAndamento = false;
    
    // Handler do formulário
    formDeposito.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const valorInput = document.getElementById('valorDeposito');
        const valor = parseFloat(valorInput.value);
        
        // Validação do valor
        if (isNaN(valor) || valor <= 0) {
            alert('Por favor, insira um valor válido.');
            return;
        }

        const minimo = parseFloat(valorInput.getAttribute('min'));
        const maximo = parseFloat(valorInput.getAttribute('max'));
        
        if (valor < minimo) {
            alert(`O valor mínimo para depósito é R$ ${minimo.toFixed(2).replace('.', ',')}`);
            return;
        }
        
        if (valor > maximo) {
            alert(`O valor máximo para depósito é R$ ${maximo.toFixed(2).replace('.', ',')}`);
            return;
        }
        
        try {
            const response = await fetch('api/deposito.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ valor })
            });
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Exibe QR Code e informações
            document.getElementById('qrCodeImage').src = data.data.qr_code;
            document.getElementById('valorPagar').textContent = 
                `R$ ${parseFloat(data.data.valor).toFixed(2).replace('.', ',')}`;
            
            // Armazena dados para copiar
            btnCopiarPix.setAttribute('data-pix', data.data.qr_code_texto);
            transacaoId = data.data.transacao_id;
            
            // Mostra etapa do PIX
            etapaValor.style.display = 'none';
            etapaPix.style.display = 'block';
            
            // Inicia verificação de status
            iniciarVerificacaoStatus();
            
        } catch (error) {
            alert(error.message || 'Erro ao gerar PIX. Tente novamente.');
        }
    });
    
    // Copiar código PIX
    btnCopiarPix.addEventListener('click', function() {
        const pixCopia = this.getAttribute('data-pix');
        navigator.clipboard.writeText(pixCopia)
            .then(() => {
                this.textContent = '✓ Código Copiado';
                setTimeout(() => {
                    this.innerHTML = '<i class="fas fa-copy"></i> Copiar Código PIX';
                }, 2000);
            })
            .catch(() => alert('Erro ao copiar. Tente novamente.'));
    });
    
    // Novo depósito
    btnNovoDeposito.addEventListener('click', function() {
        pararVerificacaoStatus();
        etapaPix.style.display = 'none';
        etapaValor.style.display = 'block';
        formDeposito.reset();
    });
    
    // Verifica status do pagamento
    function iniciarVerificacaoStatus() {
        if (intervalId) return;
        
        // Define o intervalo para verificação de status (5 segundos)
        const intervalo = 5000;
        
        intervalId = setInterval(async () => {
            try {
                const response = await fetch(`api/status_deposito.php?id=${transacaoId}`);
                const data = await response.json();
                
                if (data.status === 'aprovado') {
                    statusPagamento.className = 'alert alert-success';
                    statusPagamento.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <div>Pagamento confirmado! Redirecionando...</div>
                        </div>
                    `;
                    
                    pararVerificacaoStatus();
                    setTimeout(() => {
                        window.location.href = 'minha-conta.php';
                    }, 3000);
                }
                
            } catch (error) {
                console.error('Erro ao verificar status:', error);
            }
        }, intervalo);
    }
    
    function pararVerificacaoStatus() {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    // Botão Já Paguei
    btnJaPaguei.addEventListener('click', async function() {
        if (verificacaoManualEmAndamento) return;
        
        verificacaoManualEmAndamento = true;
        const btnText = btnJaPaguei.innerHTML;
        btnJaPaguei.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Verificando...';
        btnJaPaguei.disabled = true;
        
        try {
            const response = await fetch(`api/status_deposito.php?id=${transacaoId}&force_check=1`);
            const data = await response.json();
            
            if (data.status === 'aprovado') {
                statusPagamento.className = 'alert alert-success';
                statusPagamento.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <div>Pagamento confirmado! Redirecionando...</div>
                    </div>
                `;
                
                pararVerificacaoStatus();
                setTimeout(() => {
                    window.location.href = 'minha-conta.php';
                }, 3000);
            } else {
                statusPagamento.className = 'alert alert-warning';
                statusPagamento.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <div>Ainda não identificamos seu pagamento. Por favor, aguarde alguns instantes e tente novamente.</div>
                    </div>
                `;
                btnJaPaguei.innerHTML = btnText;
                btnJaPaguei.disabled = false;
            }
        } catch (error) {
            console.error('Erro ao verificar status:', error);
            statusPagamento.className = 'alert alert-danger';
            statusPagamento.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-times-circle me-2"></i>
                    <div>Erro ao verificar o pagamento. Por favor, tente novamente.</div>
                </div>
            `;
            btnJaPaguei.innerHTML = btnText;
            btnJaPaguei.disabled = false;
        }
        
        verificacaoManualEmAndamento = false;
    });
});
</script>

<?php include 'templates/footer.php'; ?> 