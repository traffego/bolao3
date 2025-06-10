<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
// database_functions.php não é mais necessário pois está incluído em database.php
require_once __DIR__ . '/includes/functions.php';

// Verificar se é um POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/boloes.php');
}

// Obter IDs importantes
$jogadorId = getCurrentUserId(); // ID do jogador logado
$bolaoId = isset($_POST['bolao_id']) ? (int)$_POST['bolao_id'] : 0; // ID do bolão vem do POST

// Coletar palpites do formulário
$palpites = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'resultado_') === 0) {
        $jogoId = substr($key, strlen('resultado_'));
        $palpites[$jogoId] = $value; // "1" = casa vence, "0" = empate, "2" = visitante vence
    }
}

// Verificar se o usuário já fez exatamente os mesmos palpites para este bolão
if (isset($_SESSION['user_id'])) {
    // Preparar o JSON de palpites para verificação
    $palpitesJson = json_encode(['jogos' => $palpites]);

    // Verificar se já existe um palpite idêntico
    $palpiteExistente = dbFetchOne(
        "SELECT COUNT(*) as total FROM palpites 
         WHERE bolao_id = ? 
         AND jogador_id = ? 
         AND palpites = ?",
        [$bolaoId, $jogadorId, $palpitesJson]
    );
    
    if ($palpiteExistente && $palpiteExistente['total'] > 0) {
        setFlashMessage('warning', 'Você já fez exatamente estes mesmos palpites para este bolão.');
        redirect(APP_URL . '/bolao.php?id=' . $bolaoId);
    }
}

// Buscar dados do bolão
$bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ? AND status = 1", [$bolaoId]);

// Se bolão não existe ou não está ativo
if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado ou não está ativo.');
    redirect(APP_URL . '/boloes.php');
}

// Verificar se já passou do prazo para palpites
$prazoEncerrado = false;
if (!empty($bolao['data_limite_palpitar'])) {
    $dataLimite = new DateTime($bolao['data_limite_palpitar']);
    $agora = new DateTime();
    $prazoEncerrado = $agora > $dataLimite;
}

if ($prazoEncerrado) {
    setFlashMessage('danger', 'O prazo para envio de palpites já foi encerrado.');
    redirect(APP_URL . '/bolao.php?id=' . $bolaoId);
}

// Decodificar jogos do bolão
$jogos = json_decode($bolao['jogos'], true) ?: [];

// Verificar se todos os jogos foram palpitados
if (count($palpites) !== count($jogos)) {
    setFlashMessage('warning', 'Você precisa dar palpites para todos os jogos.');
    redirect(APP_URL . '/bolao.php?id=' . $bolaoId);
}

// Preparar o JSON de palpites
$palpitesJson = json_encode(['jogos' => $palpites]);

// Debug: Mostrar valores que serão inseridos
echo "<pre>";
echo "jogadorId: " . $jogadorId . "\n";
echo "bolaoId: " . $bolaoId . "\n";
echo "palpitesJson: " . $palpitesJson . "\n";
echo "</pre>";

// Inserir palpite no banco de dados
try {
    $stmt = $pdo->prepare("
        INSERT INTO palpites (
            jogador_id,
            bolao_id,
            palpites,
            status
        ) VALUES (
            :jogador_id,
            :bolao_id,
            :palpites,
            'pendente'
        )
    ");

    $stmt->execute([
        ':jogador_id' => $jogadorId,
        ':bolao_id' => $bolaoId,
        ':palpites' => $palpitesJson
    ]);

    // Se não houver valor de participação, marcar como pago automaticamente
    if ($bolao['valor_participacao'] <= 0) {
        $palpiteId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("UPDATE palpites SET status = 'pago' WHERE id = ?");
        $stmt->execute([$palpiteId]);
    }

} catch (PDOException $e) {
    // Debug: Mostrar erro específico
    die("Erro SQL: " . $e->getMessage() . "<br>Código: " . $e->getCode());
    
    setFlashMessage('danger', 'Erro ao salvar seus palpites. Por favor, tente novamente.');
    redirect(APP_URL . '/bolao.php?id=' . $bolaoId);
}

// Helper function para obter o texto do resultado
function getResultadoTexto($tipo) {
    switch ($tipo) {
        case "1": return "Casa Vence";
        case "0": return "Empate";
        case "2": return "Visitante Vence";
        default: return "Desconhecido";
    }
}

// Helper function para obter a classe CSS do resultado
function getResultadoClasse($tipo) {
    switch ($tipo) {
        case "1": return "text-success";
        case "0": return "text-warning";
        case "2": return "text-danger";
        default: return "text-muted";
    }
}

// Verificar status do usuário logado
$usuarioLogado = isLoggedIn();
$usuarioInfo = null;

if ($usuarioLogado) {
    $usuarioInfo = dbFetchOne("SELECT nome, email, telefone FROM jogador WHERE id = ?", [getCurrentUserId()]);
}

// Título da página
$pageTitle = 'Confirmar Palpites - ' . $bolao['nome'];
include TEMPLATE_DIR . '/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if ($usuarioLogado && $bolao['valor_participacao'] > 0): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-credit-card-2-front me-2"></i>
                            Pagamento
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6 text-center mb-4 mb-md-0">
                                <?php
                                try {
                                    require_once __DIR__ . '/includes/EfiPixManager.php';
                                    
                                    // Inicializar variáveis
                                    $qrcode = '';
                                    $copiaCola = '';
                                    
                                    $efiPix = new EfiPixManager();
                                    
                                    // Cria a cobrança
                                    $charge = $efiPix->createCharge($jogadorId, $bolaoId);
                                    
                                    // Gera QR Code
                                    if (isset($charge['loc']['id'])) {
                                        $qrCodeData = $efiPix->getQrCode($charge['loc']['id']);
                                        $qrcode = $qrCodeData['imagemQrcode'];
                                        $copiaCola = $qrCodeData['qrcode'];
                                    }

                                    if (empty($qrcode) || empty($copiaCola)) {
                                        throw new Exception('QR Code ou código PIX não foram gerados corretamente.');
                                    }
                                ?>
                                <div class="bg-light p-3 rounded mb-2" style="display: inline-block;">
                                    <img src="<?= $qrcode ?>" 
                                         alt="QR Code Pagamento" 
                                         class="img-fluid"
                                         style="max-width: 200px;">
                                </div>
                                <div class="mt-3">
                                    <p class="mb-2">Ou copie o código PIX:</p>
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($copiaCola) ?>" id="pixCode" readonly>
                                        <button class="btn btn-outline-secondary copy-button" type="button" onclick="copyPixCode()">
                                            <i class="bi bi-clipboard"></i> Copiar
                                        </button>
                                    </div>
                                </div>
                                <?php
                                } catch (Exception $e) {
                                    echo '<div class="alert alert-danger">';
                                    echo 'Erro ao gerar QR Code: ' . htmlspecialchars($e->getMessage());
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            <div class="col-md-6">
                                <div class="payment-details">
                                    <h6 class="mb-3">Detalhes do Pagamento</h6>
                                    <dl class="row mb-0">
                                        <dt class="col-sm-6">Valor:</dt>
                                        <dd class="col-sm-6"><?= formatMoney($bolao['valor_participacao']) ?></dd>
                                        
                                        <dt class="col-sm-6">Bolão:</dt>
                                        <dd class="col-sm-6"><?= htmlspecialchars($bolao['nome']) ?></dd>
                                        
                                        <dt class="col-sm-6">Participante:</dt>
                                        <dd class="col-sm-6"><?= htmlspecialchars($usuarioInfo['nome']) ?></dd>
                                        
                                        <dt class="col-sm-6">Status:</dt>
                                        <dd class="col-sm-6">
                                            <span class="badge bg-warning">Aguardando Pagamento</span>
                                        </dd>
                                    </dl>

                                    <div class="payment-status p-3 rounded mb-3 bg-warning text-dark">
                                        <i class="bi bi-clock"></i> Aguardando confirmação do pagamento...
                                    </div>

                                    <div class="alert alert-info mt-3 mb-0">
                                        <small>
                                            <i class="bi bi-info-circle"></i>
                                            Após o pagamento, seus palpites serão automaticamente confirmados.
                                            O processamento pode levar até 1 minuto.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Confirmar seus Palpites</h4>
                </div>
                <div class="card-body">
                    <h5 class="mb-4">Bolão: <?= htmlspecialchars($bolao['nome']) ?></h5>

                    <?php if ($usuarioLogado): ?>
                        <div class="alert alert-success mb-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-person-check-fill me-2" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h6 class="mb-0">Logado como <?= htmlspecialchars($usuarioInfo['nome']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($usuarioInfo['email']) ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($bolao['valor_participacao'] > 0): ?>
                        <div class="alert alert-info">
                            <h5 class="alert-heading">Valor da Participação</h5>
                            <p class="mb-0">Para confirmar seus palpites, você precisa pagar <?= formatMoney($bolao['valor_participacao']) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Adicionar botão de palpites aleatórios antes da tabela -->
                    <div class="text-end mb-3">
                        <button type="button" class="btn btn-outline-primary" onclick="gerarPalpitesAleatorios()">
                            <i class="bi bi-shuffle"></i> Gerar Palpites Aleatórios
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Jogo</th>
                                    <th>Data</th>
                                    <th>Seu Palpite</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jogos as $jogo): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($jogo['time_casa']) ?> 
                                            <small>vs</small> 
                                            <?= htmlspecialchars($jogo['time_visitante']) ?>
                                        </td>
                                        <td><?= formatDateTime($jogo['data']) ?></td>
                                        <td>
                                            <input type="hidden" name="resultado_<?= $jogo['id'] ?>" value="<?= $palpites[$jogo['id']] ?? '' ?>">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-outline-success btn-sm resultado-btn <?= ($palpites[$jogo['id']] ?? '') === '1' ? 'active' : '' ?>" 
                                                        onclick="selecionarResultado(<?= $jogo['id'] ?>, '1')">
                                                    Casa Vence
                                                </button>
                                                <button type="button" class="btn btn-outline-warning btn-sm resultado-btn <?= ($palpites[$jogo['id']] ?? '') === '0' ? 'active' : '' ?>"
                                                        onclick="selecionarResultado(<?= $jogo['id'] ?>, '0')">
                                                    Empate
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm resultado-btn <?= ($palpites[$jogo['id']] ?? '') === '2' ? 'active' : '' ?>"
                                                        onclick="selecionarResultado(<?= $jogo['id'] ?>, '2')">
                                                    Visitante Vence
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="<?= APP_URL ?>/salvar-palpite.php" method="post" class="mt-4" id="formPalpites">
                        <!-- Passar todos os dados do formulário original -->
                        <?php foreach ($_POST as $key => $value): ?>
                            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endforeach; ?>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Voltar e Revisar
                            </a>
                            
                            <button type="<?= $usuarioLogado ? 'submit' : 'button' ?>" class="btn btn-success btn-lg" <?= $usuarioLogado ? '' : 'onclick="showLoginModal()"' ?>>
                                <?php if ($bolao['valor_participacao'] > 0): ?>
                                    <i class="bi bi-credit-card"></i> Pagar e Confirmar Palpites
                                <?php else: ?>
                                    <i class="bi bi-check-circle"></i> Confirmar Palpites
                                <?php endif; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Login/Cadastro -->
<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Acesso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <p>Escolha uma opção para continuar:</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="showLoginForm()">
                            <i class="bi bi-person"></i> Já sou cadastrado
                        </button>
                        <button class="btn btn-primary" onclick="showRegisterForm()">
                            <i class="bi bi-person-plus"></i> Me cadastrar
                        </button>
                    </div>
                </div>

                <!-- Formulário de Login -->
                <form id="loginForm" class="d-none" onsubmit="handleLogin(event)">
                    <h5 class="mb-3">Login</h5>
                    <div class="mb-3">
                        <label for="loginEmail" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="loginEmail" required>
                    </div>
                    <div class="mb-3">
                        <label for="loginPassword" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="loginPassword" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Entrar</button>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="#" onclick="showInitialOptions()">Voltar</a>
                    </div>
                </form>

                <!-- Formulário de Cadastro -->
                <form id="registerForm" class="d-none" onsubmit="handleRegister(event)">
                    <h5 class="mb-3">Cadastro</h5>
                    <div class="mb-3">
                        <label for="registerName" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="registerName" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerEmail" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="registerEmail" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerPhone" class="form-label">Telefone</label>
                        <input type="tel" class="form-control" id="registerPhone" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerCPF" class="form-label">CPF</label>
                        <input type="text" class="form-control" id="registerCPF" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerPassword" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="registerPassword" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Cadastrar</button>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="#" onclick="showInitialOptions()">Voltar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicialização do modal e elementos
    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const initialOptions = document.querySelector('.modal-body .text-center');

    // Função para mostrar o modal de login
    window.showLoginModal = function() {
        showInitialOptions();
        loginModal.show();
    }

    // Função para mostrar as opções iniciais
    window.showInitialOptions = function() {
        initialOptions.classList.remove('d-none');
        loginForm.classList.add('d-none');
        registerForm.classList.add('d-none');
    }

    // Função para mostrar o formulário de login
    window.showLoginForm = function() {
        initialOptions.classList.add('d-none');
        loginForm.classList.remove('d-none');
        registerForm.classList.add('d-none');
    }

    // Função para mostrar o formulário de cadastro
    window.showRegisterForm = function() {
        initialOptions.classList.add('d-none');
        loginForm.classList.add('d-none');
        registerForm.classList.remove('d-none');
    }

    // Função para lidar com o login
    window.handleLogin = async function(event) {
        event.preventDefault();
        
        const email = document.getElementById('loginEmail').value;
        const password = document.getElementById('loginPassword').value;
        
        try {
            const response = await fetch('<?= APP_URL ?>/ajax/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Recarrega a página após login bem-sucedido
                window.location.reload();
            } else {
                alert(data.message || 'Erro ao fazer login. Verifique suas credenciais.');
            }
        } catch (error) {
            alert('Erro ao fazer login. Tente novamente.');
        }
    }

    // Função para lidar com o cadastro
    window.handleRegister = async function(event) {
        event.preventDefault();
        
        const formData = {
            nome: document.getElementById('registerName').value,
            email: document.getElementById('registerEmail').value,
            telefone: document.getElementById('registerPhone').value,
            cpf: document.getElementById('registerCPF').value,
            senha: document.getElementById('registerPassword').value
        };
        
        try {
            const response = await fetch('<?= APP_URL ?>/ajax/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Recarrega a página após cadastro bem-sucedido
                window.location.reload();
            } else {
                alert(data.message || 'Erro ao fazer cadastro. Verifique os dados informados.');
            }
        } catch (error) {
            alert('Erro ao fazer cadastro. Tente novamente.');
        }
    }

    // Máscara para telefone
    const phoneInput = document.getElementById('registerPhone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 0) {
                if (value.length <= 2) {
                    value = `(${value}`;
                } else if (value.length <= 7) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
                } else {
                    value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
                }
            }
            e.target.value = value;
        });
    }

    // Máscara para CPF
    const cpfInput = document.getElementById('registerCPF');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = `${value.slice(0, 3)}.${value.slice(3)}`;
                } else if (value.length <= 9) {
                    value = `${value.slice(0, 3)}.${value.slice(3, 6)}.${value.slice(6)}`;
                } else {
                    value = `${value.slice(0, 3)}.${value.slice(3, 6)}.${value.slice(6, 9)}-${value.slice(9)}`;
                }
            }
            e.target.value = value;
        });
    }
});

// Função para copiar código PIX
function copyPixCode() {
    const pixCode = document.getElementById('pixCode');
    pixCode.select();
    document.execCommand('copy');
    
    // Feedback visual
    const button = document.querySelector('.copy-button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-check"></i> Código Copiado!';
    button.classList.add('btn-success');
    button.classList.remove('btn-outline-primary');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-primary');
    }, 2000);
}

// Verificar status do pagamento a cada 5 segundos
<?php if ($usuarioLogado && $bolao['valor_participacao'] > 0): ?>
let checkPaymentInterval;

function updatePaymentStatus(status) {
    const statusElement = document.querySelector('.payment-status');
    if (!statusElement) return;

    statusElement.className = 'payment-status p-3 rounded mb-3';
    
    switch (status) {
        case 'paid':
            statusElement.classList.add('bg-success', 'text-white');
            statusElement.innerHTML = '<i class="bi bi-check-circle"></i> Pagamento confirmado! Redirecionando...';
            break;
        case 'pending':
            statusElement.classList.add('bg-warning', 'text-dark');
            statusElement.innerHTML = '<i class="bi bi-clock"></i> Aguardando confirmação do pagamento...';
            break;
        case 'error':
            statusElement.classList.add('bg-danger', 'text-white');
            statusElement.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Erro ao verificar pagamento. Tentando novamente...';
            break;
    }
}

function checkPaymentStatus() {
    fetch('ajax/check-payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            bolao_id: <?= $bolao['id'] ?>,
            user_id: <?= getCurrentUserId() ?>,
            session_id: '<?= session_id() ?>',
            _: new Date().getTime()
        })
    })
    .then(response => response.text())
    .then(text => {
        // Se a resposta contiver HTML, provavelmente é a página de redirecionamento após o pagamento
        if (text.includes('<br') || text.includes('<b>')) {
            return { status: 'paid' };
        }
        
        // Tenta parsear como JSON
        try {
            return text ? JSON.parse(text) : { status: 'error' };
        } catch (e) {
            // Se não conseguir parsear e não for HTML, é um erro
            return { status: 'error' };
        }
    })
    .then(data => {
        if (data.status === 'paid') {
            // Limpar o intervalo imediatamente para evitar mais requisições
            clearInterval(checkPaymentInterval);
            
            // Atualizar UI
            const statusElement = document.querySelector('.payment-status');
            if (statusElement) {
                statusElement.className = 'payment-status p-3 rounded mb-3 bg-success text-white';
                statusElement.innerHTML = '<i class="bi bi-check-circle"></i> Pagamento confirmado! Redirecionando...';
            }
            
            // Redirecionar após um breve delay
            setTimeout(() => {
                window.location.href = '<?= APP_URL ?>/agradecimento.php';
            }, 2000);
        } else if (data.status === 'pending') {
            updatePaymentStatus('pending');
        }
        // Ignora silenciosamente outros status
    })
    .catch(error => {
        // Apenas log no console, sem afetar a UI
        console.log('Erro na verificação:', error);
    });
}

checkPaymentInterval = setInterval(checkPaymentStatus, 5000);
checkPaymentStatus();
<?php endif; ?>

// Adicionar script para gerar palpites aleatórios
function gerarPalpitesAleatorios() {
    const jogos = <?= json_encode(array_column($jogos, 'id')) ?>;
    jogos.forEach(jogoId => {
        const resultados = ['1', '0', '2'];
        const randomIndex = Math.floor(Math.random() * resultados.length);
        selecionarResultado(jogoId, resultados[randomIndex]);
    });
}

function selecionarResultado(jogoId, resultado) {
    // Atualizar o input hidden
    const input = document.querySelector(`input[name="resultado_${jogoId}"]`);
    input.value = resultado;
    
    // Atualizar os botões
    const btnGroup = input.nextElementSibling;
    btnGroup.querySelectorAll('.resultado-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    btnGroup.querySelector(`.resultado-btn:nth-child(${parseInt(resultado) + 1})`).classList.add('active');
}
</script>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 