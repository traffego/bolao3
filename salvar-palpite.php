<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
// database_functions.php não é mais necessário pois está incluído em database.php
require_once __DIR__ . '/includes/functions.php';

// Verificar se é um POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/boloes.php');
}

// Obter dados do formulário
$bolaoId = isset($_POST['bolao_id']) ? (int)$_POST['bolao_id'] : 0;
$bolaoSlug = $_POST['bolao_slug'] ?? '';

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
    redirect(APP_URL . '/bolao.php?slug=' . $bolaoSlug);
}

// Verificar se usuário está logado
if (!isLoggedIn()) {
    $_SESSION['palpites_temp'] = $_POST;
    $_SESSION['login_redirect'] = APP_URL . '/bolao.php?slug=' . $bolaoSlug;
    setFlashMessage('info', 'Por favor, faça login para salvar seus palpites.');
    redirect(APP_URL . '/login.php');
}

$usuarioId = getCurrentUserId();

// Verificar se usuário pode apostar (pagamento confirmado)
$usuario = dbFetchOne("SELECT pagamento_confirmado FROM jogador WHERE id = ?", [$usuarioId]);
$podeApostar = $usuario['pagamento_confirmado'];

// Se tem valor de participação e usuário não pagou
if ($bolao['valor_participacao'] > 0 && !$podeApostar) {
    $_SESSION['palpites_temp'] = $_POST;
    setFlashMessage('warning', 'Você precisa efetuar o pagamento para participar do bolão.');
    redirect(APP_URL . '/pagamento.php');
}

// Coletar palpites do formulário
$palpites = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'resultado_') === 0) {
        $jogoId = substr($key, strlen('resultado_'));
        $palpites[$jogoId] = $value; // "1" = casa vence, "0" = empate, "2" = visitante vence
    }
}

// Decodificar jogos do bolão
$jogos = json_decode($bolao['jogos'], true) ?: [];

// Verificar se todos os jogos foram palpitados
if (count($palpites) !== count($jogos)) {
    setFlashMessage('warning', 'Você precisa dar palpites para todos os jogos.');
    redirect(APP_URL . '/bolao.php?slug=' . $bolaoSlug);
}

try {
    // Iniciar transação
    dbBeginTransaction();

    // Verificar se o usuário já é participante do bolão
    $participante = dbFetchOne(
        "SELECT id FROM participacoes WHERE bolao_id = ? AND jogador_id = ?", 
        [$bolaoId, $usuarioId]
    );
    
    // Se não for participante, criar registro
    if (!$participante) {
        dbInsert('participacoes', [
            'bolao_id' => $bolaoId,
            'jogador_id' => $usuarioId,
            'data_entrada' => date('Y-m-d H:i:s'),
            'status' => 1
        ]);
    }
    
    // Verificar se já tem palpites
    $palpiteExistente = dbFetchOne(
        "SELECT id FROM palpites WHERE bolao_id = ? AND jogador_id = ?", 
        [$bolaoId, $usuarioId]
    );
    
    // Preparar dados para salvar
    $palpitesData = [
        'bolao_id' => $bolaoId,
        'jogador_id' => $usuarioId,
        'palpites' => json_encode($palpites),
        'data_palpite' => date('Y-m-d H:i:s')
    ];
    
    if ($palpiteExistente) {
        // Atualiza palpites existentes
        dbUpdate('palpites', $palpitesData, 'id = ?', [$palpiteExistente['id']]);
        $mensagem = 'Seus palpites foram atualizados com sucesso!';
    } else {
        // Insere novos palpites
        dbInsert('palpites', $palpitesData);
        $mensagem = 'Seus palpites foram registrados com sucesso!';
    }
    
    // Commit da transação
    dbCommit();
    
    // Limpar palpites temporários da sessão
    if (isset($_SESSION['palpites_temp'])) {
        unset($_SESSION['palpites_temp']);
    }
    
    setFlashMessage('success', $mensagem);
    redirect(APP_URL . '/meus-palpites.php');

} catch (Exception $e) {
    // Rollback em caso de erro
    dbRollback();
    
    setFlashMessage('danger', 'Erro ao salvar os palpites: ' . $e->getMessage());
    redirect(APP_URL . '/bolao.php?slug=' . $bolaoSlug);
} 