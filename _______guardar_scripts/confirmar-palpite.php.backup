<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
// database_functions.php não é mais necessário pois está incluído em database.php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/classes/ContaManager.php';

// Verificar se é um POST ou se tem um palpite pendente na sessão
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_SESSION['palpite_pendente'])) {
    redirect(APP_URL . '/boloes.php');
}

// Verificar se usuário está logado
$usuarioLogado = isLoggedIn();
$jogadorId = $usuarioLogado ? getCurrentUserId() : null;

// Obter IDs importantes
$bolaoId = 0;
$palpiteId = 0;
$palpites = [];

// Se for um palpite pendente da sessão
if (isset($_SESSION['palpite_pendente'])) {
    $palpiteId = $_SESSION['palpite_pendente']['id'];
    $bolaoId = $_SESSION['palpite_pendente']['bolao_id'];
    
    // Buscar palpite existente
    $palpiteExistente = dbFetchOne(
        "SELECT p.*, b.valor_participacao 
         FROM palpites p 
         JOIN dados_boloes b ON p.bolao_id = b.id 
         WHERE p.id = ? AND p.jogador_id = ?",
        [$palpiteId, $jogadorId]
    );
    
    if ($palpiteExistente) {
        $palpitesJson = json_decode($palpiteExistente['palpites'], true);
        
        // Lidar com diferentes estruturas de dados:
        // 1. {"jogos": {"id": "valor"}} - formato novo
        // 2. {"id": "valor"} - formato antigo direto
        if (isset($palpitesJson['jogos'])) {
            $palpitesRaw = $palpitesJson['jogos'];
        } else {
            // Se não tem a estrutura "jogos", assume que o JSON é direto
            $palpitesRaw = $palpitesJson ?? [];
        }
        
        // Processar palpites para lidar com diferentes formatos (com e sem prefixo 'resultado_')
        $palpites = [];
        foreach ($palpitesRaw as $key => $value) {
            // Remove o prefixo 'resultado_' se existir para normalizar as chaves
            $jogoId = str_replace('resultado_', '', $key);
            // Garantir que a chave seja string para consistência
            $palpites[(string)$jogoId] = $value;
        }
    } else {
        unset($_SESSION['palpite_pendente']);
        redirect(APP_URL . '/boloes.php');
    }
} else {
    // Lógica existente para novos palpites via POST
    $bolaoId = isset($_POST['bolao_id']) ? (int)$_POST['bolao_id'] : 0;
    
    // Coletar palpites do formulário
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'resultado_') === 0) {
            $jogoId = substr($key, strlen('resultado_'));
            $palpites[$jogoId] = $value;
        }
    }
    
    // Validar se todos os palpites têm valores válidos
    foreach ($palpites as $jogoId => $resultado) {
        if ($resultado === '' || !in_array($resultado, ['0', '1', '2'])) {
            setFlashMessage('warning', 'Você precisa dar palpites válidos para todos os jogos.');
            redirect(APP_URL . '/bolao.php?id=' . $bolaoId);
        }
    }
}

// Buscar dados do bolão
$bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ? AND status = 1", [$bolaoId]);

// Se bolão não existe ou não está ativo
if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado ou não está ativo.');
    redirect(APP_URL . '/boloes.php');
}

// Decodificar jogos do bolão
$jogos = json_decode($bolao['jogos'], true) ?: [];

// Calcular prazo limite automaticamente: 5 minutos antes do primeiro jogo
$prazoEncerrado = false;
$dataLimite = calcularPrazoLimitePalpites($jogos, $bolao['data_limite_palpitar']);

if ($dataLimite) {
    $agora = new DateTime();
    $prazoEncerrado = $agora > $dataLimite;
}

if ($prazoEncerrado) {
    setFlashMessage('danger', 'O prazo para envio de palpites já foi encerrado.');
    redirect(APP_URL . '/bolao.php?id=' . $bolaoId);
}

// Se não for um palpite pendente, verificar se todos os jogos foram palpitados
if (!isset($_SESSION['palpite_pendente'])) {
    if (count($palpites) !== count($jogos)) {
        setFlashMessage('warning', 'Você precisa dar palpites para todos os jogos.');
        redirect(APP_URL . '/bolao.php?id=' . $bolaoId);
    }

    // Preparar o JSON de palpites
    $palpitesJson = json_encode(['jogos' => $palpites]);

    // Verificar se o usuário já tem palpites pagos idênticos para este bolão
    if ($jogadorId) {
        // Buscar palpites pagos existentes do usuário para este bolão
        $palpitesExistentes = dbFetchAll(
            "SELECT palpites FROM palpites WHERE bolao_id = ? AND jogador_id = ? AND status = 'pago'",
            [$bolaoId, $jogadorId]
        );
        
        // Verificar se algum dos palpites existentes é idêntico ao que está sendo enviado
        $palpitesSemPrefixo = [];
        foreach ($palpites as $jogoId => $resultado) {
            // Remover prefixo 'resultado_' se existir
            $jogoIdSemPrefixo = preg_replace('/^resultado_/', '', $jogoId);
            $palpitesSemPrefixo[$jogoIdSemPrefixo] = $resultado;
        }
        
        $palpiteIdentico = false;
        foreach ($palpitesExistentes as $palpiteExistente) {
            $palpitesJsonExistente = json_decode($palpiteExistente['palpites'], true);
            $palpitesJogosExistente = $palpitesJsonExistente['jogos'] ?? [];
            
            // Remover prefixo 'resultado_' dos IDs dos palpites existentes
            $palpitesExistentesSemPrefixo = [];
            foreach ($palpitesJogosExistente as $jogoId => $resultado) {
                $jogoIdSemPrefixo = preg_replace('/^resultado_/', '', $jogoId);
                $palpitesExistentesSemPrefixo[$jogoIdSemPrefixo] = $resultado;
            }
            
            // Comparar os palpites (considerando apenas os jogos do bolão atual)
            if ($palpitesSemPrefixo == $palpitesExistentesSemPrefixo) {
                $palpiteIdentico = true;
                break;
            }
        }
        
        if ($palpiteIdentico) {
            setFlashMessage('danger', 'Você já tem palpites idênticos registrados para este bolão.');
            redirect(APP_URL . '/bolao.php?id=' . $bolaoId);
        }
    }

    try {
        // Sempre inserir como pendente inicialmente
        $stmt = $pdo->prepare("
            INSERT INTO palpites (
                jogador_id,
                bolao_id,
                palpites,
                status,
                data_palpite
            ) VALUES (
                :jogador_id,
                :bolao_id,
                :palpites,
                'pendente',
                NOW()
            )
        ");

        $stmt->execute([
            ':jogador_id' => $jogadorId,
            ':bolao_id' => $bolaoId,
            ':palpites' => $palpitesJson
        ]);

        $palpiteId = $pdo->lastInsertId();

        // Se não houver valor de participação, atualizar para pago e redirecionar
        if ($bolao['valor_participacao'] <= 0) {
            $stmt = $pdo->prepare("UPDATE palpites SET status = 'pago' WHERE id = ?");
            $stmt->execute([$palpiteId]);
            
            setFlashMessage('success', 'Seus palpites foram registrados com sucesso!');
            redirect(APP_URL . '/agradecimento.php');
        }
    } catch (PDOException $e) {
        error_log("Erro ao salvar palpite: " . $e->getMessage());
        setFlashMessage('danger', 'Erro ao salvar seus palpites. Por favor, tente novamente.');
        redirect(APP_URL . '/bolao.php?id=' . $bolaoId);
    }
}

// Se usuário está logado, buscar informações da conta
$contaManager = null;
$conta = null;
if ($usuarioLogado) {
    $contaManager = new ContaManager();
    $conta = $contaManager->buscarContaPorJogador($jogadorId);
}

// Verificar modelo de pagamento
$modeloPagamento = getModeloPagamento();
$podeApostar = false;

if ($modeloPagamento === 'conta_saldo') {
    // Verificar se tem saldo suficiente
    $podeApostar = $conta && $conta['saldo'] >= $bolao['valor_participacao'];
} else {
    // No modelo por_aposta, pode apostar se estiver logado
    $podeApostar = $usuarioLogado;
}

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
                    <div class="card-header bg-green text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-credit-card-2-front me-2"></i>
                            Pagamento
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($modeloPagamento === 'conta_saldo'): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1">Saldo Disponível</h6>
                                    <h4 class="mb-0 text-success">R$ <?= number_format($conta['saldo'], 2, ',', '.') ?></h4>
                                </div>
                                <div>
                                    <h6 class="mb-1">Valor do Bolão</h6>
                                    <h4 class="mb-0">R$ <?= number_format($bolao['valor_participacao'], 2, ',', '.') ?></h4>
                                </div>
                            </div>
                            <?php if (!$podeApostar): ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Saldo insuficiente. <a href="<?= APP_URL ?>/minha-conta.php" class="alert-link">Faça um depósito</a> para participar.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center mb-3">
                                <h4 class="mb-0">R$ <?= number_format($bolao['valor_participacao'], 2, ',', '.') ?></h4>
                                <small class="text-muted">Valor para participar do bolão</small>
                            </div>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                O pagamento será solicitado após a confirmação dos palpites.
                            </div>
                        <?php endif; ?>
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
                                        <td><?= formatDateTime($jogo['data_formatada'] ?? $jogo['data']) ?></td>
                                        <td>
                                            <?php $jogoIdStr = (string)$jogo['id']; ?>
                                            <input type="hidden" name="resultado_<?= $jogo['id'] ?>" value="<?= $palpites[$jogoIdStr] ?? '' ?>">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-outline-success btn-sm resultado-btn <?= ($palpites[$jogoIdStr] ?? '') === '1' ? 'active' : '' ?>" 
                                                        onclick="selecionarResultado(<?= $jogo['id'] ?>, '1')">
                                                    Casa Vence
                                                </button>
                                                <button type="button" class="btn btn-outline-warning btn-sm resultado-btn <?= ($palpites[$jogoIdStr] ?? '') === '0' ? 'active' : '' ?>"
                                                        onclick="selecionarResultado(<?= $jogo['id'] ?>, '0')">
                                                    Empate
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm resultado-btn <?= ($palpites[$jogoIdStr] ?? '') === '2' ? 'active' : '' ?>"
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
                            
                            <button type="<?= $usuarioLogado ? 'submit' : 'button' ?>" 
                                    class="btn btn-success btn-lg" 
                                    <?= ($usuarioLogado && $podeApostar) ? '' : 'disabled' ?> 
                                    <?= $usuarioLogado ? '' : 'onclick="showLoginModal()"' ?>>
                                <?php if ($bolao['valor_participacao'] > 0): ?>
                                    <?php if ($modeloPagamento === 'conta_saldo'): ?>
                                        <i class="bi bi-check-circle"></i> Confirmar e Debitar Saldo
                                    <?php else: ?>
                                        <i class="bi bi-credit-card"></i> Confirmar e Pagar
                                    <?php endif; ?>
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

// Função para copiar o código PIX
function copyPixCode() {
    const pixCode = document.getElementById('pixCode');
    pixCode.select();
    document.execCommand('copy');
    
    const copyButton = document.querySelector('.copy-button');
    const originalText = copyButton.innerHTML;
    copyButton.innerHTML = '<i class="bi bi-check"></i> Copiado!';
    
    setTimeout(() => {
        copyButton.innerHTML = originalText;
    }, 2000);
}

// Função para verificar o status do pagamento
function checkPaymentStatus() {
    const userId = <?= $jogadorId ?>;
    const bolaoId = <?= $bolaoId ?>;
    const sessionId = '<?= session_id() ?>';

    fetch('<?= APP_URL ?>/ajax/check-payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId,
            bolao_id: bolaoId,
            session_id: sessionId
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Payment status:', data);
        
        const paymentStatus = document.querySelector('.payment-status');
        const statusBadge = document.querySelector('.badge');
        
        if (data.status === 'paid') {
            // Atualizar interface para mostrar pagamento confirmado
            if (paymentStatus) {
                paymentStatus.className = 'payment-status p-3 rounded mb-3 bg-success text-white';
                paymentStatus.innerHTML = '<i class="bi bi-check-circle"></i> Pagamento confirmado!';
            }
            
            // Atualizar badge de status
            if (statusBadge) {
                statusBadge.className = 'badge bg-success';
                statusBadge.textContent = 'Pago';
            }
            
            // Redirecionar para página de agradecimento
            window.location.href = '<?= APP_URL ?>/agradecimento.php';
            
            // Parar a verificação
            clearInterval(checkInterval);
        } else if (data.status === 'cancelled') {
            // Atualizar interface para mostrar pagamento cancelado
            if (paymentStatus) {
                paymentStatus.className = 'payment-status p-3 rounded mb-3 bg-danger text-white';
                paymentStatus.innerHTML = '<i class="bi bi-x-circle"></i> ' + (data.message || 'Pagamento cancelado');
            }
            
            // Atualizar badge de status
            if (statusBadge) {
                statusBadge.className = 'badge bg-danger';
                statusBadge.textContent = 'Cancelado';
            }
            
            // Parar a verificação
            clearInterval(checkInterval);
        } else if (data.status === 'pending') {
            // Atualizar interface para mostrar pagamento pendente
            if (paymentStatus) {
                paymentStatus.className = 'payment-status p-3 rounded mb-3 bg-warning text-dark';
                paymentStatus.innerHTML = '<i class="bi bi-clock"></i> ' + (data.message || 'Aguardando confirmação do pagamento...');
            }
            
            // Atualizar badge de status
            if (statusBadge) {
                statusBadge.className = 'badge bg-warning text-dark';
                statusBadge.textContent = 'Pendente';
            }
        } else if (data.status === 'error') {
            // Atualizar interface para mostrar erro
            if (paymentStatus) {
                paymentStatus.className = 'payment-status p-3 rounded mb-3 bg-danger text-white';
                paymentStatus.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + (data.message || 'Erro ao verificar pagamento');
            }
            
            // Atualizar badge de status
            if (statusBadge) {
                statusBadge.className = 'badge bg-danger';
                statusBadge.textContent = 'Erro';
            }
            
            // Parar a verificação em caso de erro
            clearInterval(checkInterval);
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        
        // Mostrar erro na interface
        const paymentStatus = document.querySelector('.payment-status');
        if (paymentStatus) {
            paymentStatus.className = 'payment-status p-3 rounded mb-3 bg-danger text-white';
            paymentStatus.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Erro ao verificar pagamento';
        }
    });
}

// Iniciar verificação a cada 5 segundos
const checkInterval = setInterval(checkPaymentStatus, 5000);

// Verificar imediatamente ao carregar a página
checkPaymentStatus();

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