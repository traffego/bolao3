<?php
require_once 'config/config.php';require_once 'includes/functions.php';

// Page title
$pageTitle = 'Como Funciona';

// Include header
include TEMPLATE_DIR . '/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Como Funciona o Bolão Football</h1>
    
    <!-- Seção Principal -->
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h4 mb-3">Participar é muito fácil!</h2>
            <p>O Bolão Football é uma plataforma onde você pode participar de bolões de futebol e concorrer a prêmios em dinheiro. Siga os passos abaixo para começar:</p>
        </div>
    </div>

    <!-- Passos -->
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h5 mb-3"><i class="bi bi-1-circle-fill text-primary me-2"></i>Cadastro</h3>
                    <p>Crie sua conta gratuitamente fornecendo apenas informações básicas como nome, e-mail e senha. O processo é rápido e seguro.</p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h5 mb-3"><i class="bi bi-2-circle-fill text-primary me-2"></i>Escolha um Bolão</h3>
                    <p>Navegue pelos bolões disponíveis e escolha aquele que mais te interessa. Cada bolão tem suas próprias regras e premiações.</p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h5 mb-3"><i class="bi bi-3-circle-fill text-primary me-2"></i>Faça o Pagamento</h3>
                    <p>Efetue o pagamento da taxa de participação de forma segura através do PIX. O valor varia de acordo com o bolão escolhido.</p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h5 mb-3"><i class="bi bi-4-circle-fill text-primary me-2"></i>Registre seus Palpites</h3>
                    <p>Faça seus palpites para os jogos do bolão. Você pode alterar seus palpites até o início de cada partida.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Regras Gerais -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h3 class="h5 mb-0">Regras Gerais</h3>
        </div>
        <div class="card-body">
            <ul class="list-unstyled">
                <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Pontuação:
                    <ul>
                        <li>Acertar o placar exato: 10 pontos</li>
                        <li>Acertar o vencedor e diferença de gols: 5 pontos</li>
                        <li>Acertar apenas o vencedor: 3 pontos</li>
                    </ul>
                </li>
                <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Os palpites podem ser alterados até o início de cada partida</li>
                <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Em caso de empate no número de pontos, o critério de desempate será o maior número de placares exatos</li>
                <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>O prêmio será distribuído conforme as regras específicas de cada bolão</li>
            </ul>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="text-center">
        <a href="<?= APP_URL ?>/boloes.php" class="btn btn-primary btn-lg">Participar Agora</a>
    </div>
</div>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 