<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/classes/ContaManager.php';

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

// Verificar modelo de pagamento
$modeloPagamento = getModeloPagamento();
$contaManager = new ContaManager();

// Definir status inicial do palpite
$statusPagamento = 'pendente';

// Se tem valor de participação, verificar pagamento
if ($bolao['valor_participacao'] > 0) {
    if ($modeloPagamento === 'conta_saldo') {
        // Verificar se tem saldo suficiente
        $saldoInfo = verificarSaldoJogador($usuarioId);
        if (!$saldoInfo['tem_saldo'] || $saldoInfo['saldo_atual'] < $bolao['valor_participacao']) {
            setFlashMessage('danger', 'Saldo insuficiente para participar do bolão.');
            redirect(APP_URL . '/minha-conta.php');
        }
        $contaId = $saldoInfo['conta_id'];
    }
} else {
    $statusPagamento = 'pago'; // Bolão gratuito
}

// Iniciar transação
dbBeginTransaction();

try {
    // Inserir palpites
    $palpiteId = dbInsert('palpites', [
        'jogador_id' => $usuarioId,
        'bolao_id' => $bolaoId,
        'status' => $statusPagamento,
        'data_palpite' => date('Y-m-d H:i:s')
    ]);

    // Inserir resultados
    $stmt = dbPrepare("INSERT INTO palpites_resultados (palpite_id, partida_id, resultado) VALUES (?, ?, ?)");
    
    // Verificar se a preparação da query foi bem-sucedida
    if ($stmt === false) {
        throw new Exception('Erro ao preparar query para inserção de resultados.');
    }
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'resultado_') === 0) {
            $partidaId = substr($key, strlen('resultado_'));
            // Verificar se execute foi bem-sucedido
            if (!$stmt->execute([$palpiteId, $partidaId, $value])) {
                throw new Exception('Erro ao inserir resultado para a partida ' . $partidaId);
            }
        }
    }

    // Processar pagamento conforme modelo
    if ($bolao['valor_participacao'] > 0) {
        if ($modeloPagamento === 'conta_saldo') {
            // Criar transação de débito
            $transacao = criarTransacaoPalpite(
                $contaId,
                $bolao['valor_participacao'],
                $palpiteId
            );

            if ($transacao === false) {
                throw new Exception('Erro ao processar pagamento com saldo.');
            }

            // Atualizar status do palpite
            dbExecute("UPDATE palpites SET status = 'pago' WHERE id = ?", [$palpiteId]);
            $statusPagamento = 'pago';

            // Registrar log
            dbInsert('logs', [
                'tipo' => 'pagamento',
                'descricao' => "Pagamento automático do palpite #$palpiteId usando saldo da conta #$contaId",
                'jogador_id' => $usuarioId,
                'data_hora' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Salvar informações para pagamento
            $_SESSION['palpite_pendente'] = [
                'id' => $palpiteId,
                'bolao_id' => $bolaoId,
                'valor' => $bolao['valor_participacao']
            ];
        }
    }

    // Commit da transação
    dbCommit();

    // Redirecionar conforme status
    if ($statusPagamento === 'pago') {
        setFlashMessage('success', 'Palpites registrados com sucesso!');
        redirect(APP_URL . '/meus-palpites.php');
    } else {
        redirect(APP_URL . '/pagamento.php');
    }

} catch (Exception $e) {
    dbRollback();
    error_log('Erro ao salvar palpite: ' . $e->getMessage());
    setFlashMessage('danger', 'Erro ao salvar palpites: ' . $e->getMessage());
    redirect(APP_URL . '/bolao.php?slug=' . $bolaoSlug);
} 