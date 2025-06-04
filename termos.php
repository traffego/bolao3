<?php
require_once 'config/config.php';require_once 'includes/functions.php';

// Page title
$pageTitle = 'Termos de Uso';

// Include header
include TEMPLATE_DIR . '/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Termos de Uso</h1>
    
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">1. Aceitação dos Termos</h2>
            <p>Ao acessar e usar o Bolão Football, você concorda com estes termos de uso. Se você não concordar com qualquer parte destes termos, não deverá usar nosso serviço.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">2. Elegibilidade</h2>
            <p>Para usar o Bolão Football, você deve:</p>
            <ul>
                <li>Ter pelo menos 18 anos de idade</li>
                <li>Fornecer informações verdadeiras e precisas durante o cadastro</li>
                <li>Manter suas informações de conta atualizadas</li>
                <li>Ser responsável pela confidencialidade de sua senha</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">3. Regras de Uso</h2>
            <p>Ao usar o Bolão Football, você concorda em:</p>
            <ul>
                <li>Não usar o serviço para fins ilegais</li>
                <li>Não tentar manipular resultados ou pontuações</li>
                <li>Não criar múltiplas contas</li>
                <li>Não compartilhar sua conta com terceiros</li>
                <li>Respeitar outros usuários e administradores</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">4. Pagamentos e Reembolsos</h2>
            <p>Sobre pagamentos e reembolsos:</p>
            <ul>
                <li>Todos os pagamentos são processados de forma segura via PIX</li>
                <li>O valor da participação é não reembolsável após o início do bolão</li>
                <li>Reembolsos podem ser solicitados apenas se o bolão for cancelado</li>
                <li>Os prêmios serão pagos conforme as regras específicas de cada bolão</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">5. Privacidade</h2>
            <p>Nós respeitamos sua privacidade e protegemos seus dados pessoais. Para mais informações, consulte nossa <a href="<?= APP_URL ?>/privacidade.php">Política de Privacidade</a>.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">6. Modificações dos Termos</h2>
            <p>O Bolão Football se reserva o direito de modificar estes termos a qualquer momento. Alterações significativas serão notificadas aos usuários por e-mail ou através do site.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">7. Contato</h2>
            <p>Se você tiver dúvidas sobre estes termos, entre em contato com nossa equipe de suporte.</p>
        </div>
    </div>

    <!-- Última atualização -->
    <p class="text-muted text-center">Última atualização: <?= date('d/m/Y') ?></p>
</div>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 