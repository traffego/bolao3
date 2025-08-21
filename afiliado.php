<?php
/**
 * Área do Afiliado - Bolão Vitimba
 * Dashboard exclusivo para afiliados ativos
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

// Verificar se está logado
if (!isLoggedIn()) {
    setFlashMessage('warning', 'Você precisa estar logado para acessar a área de afiliados.');
    redirect(APP_URL . '/login.php');
}

// Verificar se é afiliado ativo
if (!isActiveAffiliate()) {
    setFlashMessage('danger', 'Acesso negado. Esta área é exclusiva para afiliados ativos.');
    redirect(APP_URL . '/minha-conta.php');
}

$userId = getCurrentUserId();

// Buscar dados do afiliado
$afiliado = dbFetchOne(
    "SELECT id, nome, email, codigo_afiliado, data_cadastro, afiliado_ativo 
     FROM jogador 
     WHERE id = ?",
    [$userId]
);

// Buscar estatísticas do afiliado
$stats = [
    'total_indicacoes' => 0,
    'indicacoes_ativas' => 0,
    'indicacoes_mes' => 0,
    'comissao_total' => 0
];

// Total de indicações
$totalIndicacoes = dbFetchOne(
    "SELECT COUNT(*) as total FROM jogador WHERE ref_indicacao = ?",
    [$afiliado['codigo_afiliado']]
);
$stats['total_indicacoes'] = $totalIndicacoes['total'] ?? 0;

// Indicações ativas
$indicacoesAtivas = dbFetchOne(
    "SELECT COUNT(*) as total FROM jogador WHERE ref_indicacao = ? AND status = 'ativo'",
    [$afiliado['codigo_afiliado']]
);
$stats['indicacoes_ativas'] = $indicacoesAtivas['total'] ?? 0;

// Indicações do mês atual
$indicacoesMes = dbFetchOne(
    "SELECT COUNT(*) as total FROM jogador 
     WHERE ref_indicacao = ? 
     AND YEAR(data_cadastro) = YEAR(CURDATE()) 
     AND MONTH(data_cadastro) = MONTH(CURDATE())",
    [$afiliado['codigo_afiliado']]
);
$stats['indicacoes_mes'] = $indicacoesMes['total'] ?? 0;

// Buscar últimas indicações
$ultimasIndicacoes = dbFetchAll(
    "SELECT id, nome, email, data_cadastro, status 
     FROM jogador 
     WHERE ref_indicacao = ? 
     ORDER BY data_cadastro DESC 
     LIMIT 10",
    [$afiliado['codigo_afiliado']]
);

// Título da página
$pageTitle = 'Área do Afiliado';
include 'templates/header.php';
?>

<div class="container py-4">
    <!-- Hero Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body py-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h3 mb-2">
                                <i class="bi bi-people-fill me-2"></i>
                                Área do Afiliado
                            </h1>
                            <p class="mb-0">Bem-vindo, <?= sanitize($afiliado['nome']) ?>! Gerencie suas indicações e acompanhe seus ganhos.</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="badge bg-light text-primary fs-6 px-3 py-2">
                                <i class="bi bi-award-fill me-1"></i>
                                Afiliado Ativo
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-primary mb-2">
                        <i class="bi bi-people" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="h4 mb-1"><?= $stats['total_indicacoes'] ?></h3>
                    <p class="text-muted mb-0">Total de Indicações</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="bi bi-person-check" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="h4 mb-1"><?= $stats['indicacoes_ativas'] ?></h3>
                    <p class="text-muted mb-0">Indicações Ativas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-info mb-2">
                        <i class="bi bi-calendar-month" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="h4 mb-1"><?= $stats['indicacoes_mes'] ?></h3>
                    <p class="text-muted mb-0">Indicações este Mês</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-warning mb-2">
                        <i class="bi bi-currency-dollar" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="h4 mb-1"><?= formatMoney($stats['comissao_total']) ?></h3>
                    <p class="text-muted mb-0">Comissão Total</p>
                    <small class="text-muted">(Em breve)</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Seu Código de Afiliado -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-qr-code me-2"></i>
                        Seu Código de Afiliado
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Código de Afiliado:</label>
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   value="<?= sanitize($afiliado['codigo_afiliado']) ?>" 
                                   readonly 
                                   id="codigoAfiliado">
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    onclick="copiarCodigo()" 
                                    title="Copiar código">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Link de Indicação:</label>
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   value="<?= APP_URL ?>/cadastro.php?ref=<?= sanitize($afiliado['codigo_afiliado']) ?>" 
                                   readonly 
                                   id="linkIndicacao">
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    onclick="copiarLink()" 
                                    title="Copiar link">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Como funciona:</strong> Compartilhe seu link de indicação. Quando alguém se cadastrar usando seu link, você ganhará comissões sobre as atividades dessa pessoa.
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas Indicações -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Últimas Indicações
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($ultimasIndicacoes)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-person-plus text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">Você ainda não possui indicações.</p>
                            <p class="text-muted">Compartilhe seu link para começar a ganhar!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Data</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimasIndicacoes as $indicacao): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?= sanitize($indicacao['nome']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= sanitize($indicacao['email']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?= formatDate($indicacao['data_cadastro']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($indicacao['status'] === 'ativo'): ?>
                                                    <span class="badge bg-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?= ucfirst($indicacao['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($ultimasIndicacoes) >= 10): ?>
                            <div class="text-center mt-3">
                                <small class="text-muted">Mostrando as 10 indicações mais recentes</small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Ações Rápidas -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning-fill me-2"></i>
                        Ações Rápidas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <button class="btn btn-primary w-100" onclick="compartilharWhatsApp()">
                                <i class="bi bi-whatsapp me-2"></i>
                                Compartilhar no WhatsApp
                            </button>
                        </div>
                        <div class="col-md-4 mb-3">
                            <button class="btn btn-info w-100" onclick="compartilharTelegram()">
                                <i class="bi bi-telegram me-2"></i>
                                Compartilhar no Telegram
                            </button>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="<?= APP_URL ?>/minha-conta.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-person-gear me-2"></i>
                                Minha Conta
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copiarCodigo() {
    const codigo = document.getElementById('codigoAfiliado');
    codigo.select();
    codigo.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(codigo.value).then(() => {
        mostrarToast('Código copiado!', 'success');
    }).catch(() => {
        // Fallback para navegadores mais antigos
        document.execCommand('copy');
        mostrarToast('Código copiado!', 'success');
    });
}

function copiarLink() {
    const link = document.getElementById('linkIndicacao');
    link.select();
    link.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(link.value).then(() => {
        mostrarToast('Link copiado!', 'success');
    }).catch(() => {
        // Fallback para navegadores mais antigos
        document.execCommand('copy');
        mostrarToast('Link copiado!', 'success');
    });
}

function compartilharWhatsApp() {
    const link = document.getElementById('linkIndicacao').value;
    const texto = `🎯 Venha participar do melhor bolão de futebol! Use meu código de indicação e ganhe bônus especiais: ${link}`;
    const url = `https://wa.me/?text=${encodeURIComponent(texto)}`;
    window.open(url, '_blank');
}

function compartilharTelegram() {
    const link = document.getElementById('linkIndicacao').value;
    const texto = `🎯 Venha participar do melhor bolão de futebol! Use meu código de indicação e ganhe bônus especiais: ${link}`;
    const url = `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent(texto)}`;
    window.open(url, '_blank');
}

function mostrarToast(mensagem, tipo = 'info') {
    // Criar elemento do toast
    const toast = document.createElement('div');
    toast.className = `alert alert-${tipo} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
    toast.innerHTML = `
        ${mensagem}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    // Adicionar ao body
    document.body.appendChild(toast);
    
    // Remover automaticamente após 3 segundos
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 3000);
}
</script>

<?php include 'templates/footer.php'; ?>