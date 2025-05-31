<?php
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Page title
$pageTitle = 'Política de Privacidade';

// Include header
include TEMPLATE_DIR . '/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Política de Privacidade</h1>
    
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">1. Informações Coletadas</h2>
            <p>Coletamos as seguintes informações quando você usa o Bolão Football:</p>
            <ul>
                <li>Nome completo</li>
                <li>Endereço de e-mail</li>
                <li>Data de nascimento</li>
                <li>Informações de pagamento (processadas de forma segura)</li>
                <li>Dados de uso do site</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">2. Uso das Informações</h2>
            <p>Utilizamos suas informações para:</p>
            <ul>
                <li>Gerenciar sua conta e participação nos bolões</li>
                <li>Processar pagamentos e distribuir prêmios</li>
                <li>Enviar notificações importantes sobre os bolões</li>
                <li>Melhorar nossos serviços</li>
                <li>Prevenir fraudes e atividades suspeitas</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">3. Compartilhamento de Dados</h2>
            <p>Suas informações pessoais não são vendidas ou compartilhadas com terceiros, exceto:</p>
            <ul>
                <li>Quando necessário para processar pagamentos</li>
                <li>Para cumprir obrigações legais</li>
                <li>Com seu consentimento expresso</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">4. Segurança dos Dados</h2>
            <p>Protegemos suas informações através de:</p>
            <ul>
                <li>Criptografia SSL/TLS</li>
                <li>Acesso restrito a dados sensíveis</li>
                <li>Monitoramento regular de segurança</li>
                <li>Backups periódicos</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">5. Seus Direitos</h2>
            <p>Você tem direito a:</p>
            <ul>
                <li>Acessar seus dados pessoais</li>
                <li>Corrigir informações incorretas</li>
                <li>Solicitar a exclusão de seus dados</li>
                <li>Exportar seus dados</li>
                <li>Retirar seu consentimento</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">6. Cookies</h2>
            <p>Utilizamos cookies para:</p>
            <ul>
                <li>Manter sua sessão ativa</li>
                <li>Lembrar suas preferências</li>
                <li>Melhorar a experiência do usuário</li>
                <li>Coletar dados estatísticos anônimos</li>
            </ul>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">7. Contato</h2>
            <p>Para questões relacionadas à privacidade, entre em contato através do e-mail: privacidade@bolao-football.com</p>
        </div>
    </div>

    <!-- Última atualização -->
    <p class="text-muted text-center">Última atualização: <?= date('d/m/Y') ?></p>
</div>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 