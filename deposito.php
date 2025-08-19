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

// Verifica se há valor sugerido via URL (para pagamentos)
$valorSugerido = filter_input(INPUT_GET, 'valor', FILTER_VALIDATE_FLOAT);
if ($valorSugerido && $valorSugerido >= $configs['deposito_minimo'] && $valorSugerido <= $configs['deposito_maximo']) {
    $valorPadrao = $valorSugerido;
} else {
    $valorPadrao = null;
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
                                           value="<?= $transacao ? number_format($transacao['valor'], 2, '.', '') : ($valorPadrao ? number_format($valorPadrao, 2, '.', '') : '') ?>"
                                           <?= $transacao ? 'readonly' : '' ?>
                                           required>
                                </div>
                                <div class="form-text">
                                    <?php if ($transacao): ?>
                                        <i class="fas fa-info-circle"></i> Retomando pagamento pendente
                                    <?php elseif ($valorPadrao): ?>
                                        <i class="bi bi-info-circle text-primary"></i> Valor necessário para completar seu pagamento
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
    const btnGerarPix = formDeposito.querySelector('button[type="submit"]');
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
        
        // Adiciona loading otimizado (considera se é retomada ou novo)
        const isRetomada = btnGerarPix.innerHTML.includes('Retomar');
        const originalText = btnGerarPix.innerHTML;
        
        // Indicador de progresso mais detalhado
        let progressStep = 0;
        const progressMessages = isRetomada ? 
            ['Retomando...', 'Validando dados...', 'Processando...'] :
            ['Gerando PIX...', 'Criando cobrança...', 'Gerando QR Code...'];
        
        const updateProgress = () => {
            if (progressStep < progressMessages.length) {
                btnGerarPix.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status"></span>${progressMessages[progressStep]}`;
                progressStep++;
            }
        };
        
        updateProgress();
        btnGerarPix.disabled = true;
        
        // Atualiza progresso a cada 1.5 segundos para melhor UX
        const progressInterval = setInterval(updateProgress, 1500);
        
        try {
            const response = await fetch('api/deposito.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ valor })
            });
            
            const responseText = await response.text();
            let data;
            
            try {
                data = JSON.parse(responseText);
            } catch (jsonError) {
                console.error('Erro de JSON:', responseText);
                throw new Error('Resposta inválida do servidor. Verifique os logs.');
            }
            
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
        } finally {
            // Limpa o intervalo de progresso
            clearInterval(progressInterval);
            
            // Remove loading
            btnGerarPix.innerHTML = originalText;
            btnGerarPix.disabled = false;
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
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('Erro de JSON no status:', responseText);
                    return; // Pula esta verificação e tenta na próxima
                }
                
                if (data.status === 'aprovado') {
                    pararVerificacaoStatus();
                    mostrarSucessoPagamento();
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
            const responseText = await response.text();
            let data;
            
            try {
                data = JSON.parse(responseText);
            } catch (jsonError) {
                console.error('Erro de JSON no force_check:', responseText);
                throw new Error('Erro na verificação do pagamento.');
            }
            
            if (data.status === 'aprovado') {
                pararVerificacaoStatus();
                mostrarSucessoPagamento();
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

    // Função para mostrar sucesso do pagamento com efeitos visuais
    function mostrarSucessoPagamento() {
        // Cria o overlay e modal de sucesso
        const overlay = document.createElement('div');
        overlay.className = 'payment-success-overlay';
        
        const modal = document.createElement('div');
        modal.className = 'success-modal';
        
        modal.innerHTML = `
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="success-title">Pagamento Confirmado!</div>
            <div class="success-message">
                Seu depósito foi processado com sucesso.<br>
                O valor já está disponível em sua conta.
            </div>
            <div class="btn btn-success btn-lg" onclick="redirecionarParaConta()">
                <i class="fas fa-arrow-right me-2"></i>Ir para Minha Conta
            </div>
        `;
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        // Cria confetes
        criarConfetes();
        
        // Toca som de sucesso (se suportado)
        tocarSomSucesso();
        
        // Auto-redireciona após 5 segundos
        setTimeout(() => {
            redirecionarParaConta();
        }, 5000);
    }
    
    function criarConfetes() {
        const cores = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#6c5ce7', '#00b894', '#e84393'];
        
        for (let i = 0; i < 50; i++) {
            setTimeout(() => {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.background = cores[Math.floor(Math.random() * cores.length)];
                confetti.style.animationDelay = Math.random() * 3 + 's';
                confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                
                document.body.appendChild(confetti);
                
                // Remove confete após animação
                setTimeout(() => {
                    if (confetti.parentNode) {
                        confetti.parentNode.removeChild(confetti);
                    }
                }, 5000);
            }, i * 100);
        }
    }
    
    function tocarSomSucesso() {
        try {
            // Cria um contexto de áudio para tocar um som de sucesso
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            // Frequências para um acorde de sucesso (Dó maior)
            const frequencies = [523.25, 659.25, 783.99]; // C5, E5, G5
            
            frequencies.forEach((freq, index) => {
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.setValueAtTime(freq, audioContext.currentTime);
                oscillator.type = 'sine';
                
                gainNode.gain.setValueAtTime(0, audioContext.currentTime);
                gainNode.gain.linearRampToValueAtTime(0.1, audioContext.currentTime + 0.1);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.8);
                
                oscillator.start(audioContext.currentTime + index * 0.1);
                oscillator.stop(audioContext.currentTime + 0.8 + index * 0.1);
            });
        } catch (error) {
            console.log('Som de sucesso não suportado:', error);
        }
    }
    
    window.redirecionarParaConta = function() {
        // Verificar se há palpites preservados para restaurar
        fetch('api/verificar_palpites_temp.php')
            .then(response => response.json())
            .then(data => {
                if (data.tem_palpites && data.bolao_redirect) {
                    // Redirecionar de volta ao bolão com palpites preservados
                    window.location.href = data.bolao_redirect;
                } else {
                    // Redirecionamento normal para minha conta
                    window.location.href = 'minha-conta.php';
                }
            })
            .catch(error => {
                console.error('Erro ao verificar palpites:', error);
                // Fallback para minha conta em caso de erro
                window.location.href = 'minha-conta.php';
            });
    };
});
</script>

<?php include 'templates/footer.php'; ?>