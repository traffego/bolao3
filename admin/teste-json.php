<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar se o administrador está logado
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Obter e validar ID do bolão
$bolaoId = filter_input(INPUT_GET, 'bolao_id', FILTER_VALIDATE_INT);
if (!$bolaoId) {
    setFlashMessage('danger', 'ID do bolão inválido.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Buscar dados do bolão
$bolao = dbFetchOne(
    "SELECT * FROM dados_boloes WHERE id = ?", 
    [$bolaoId]
);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Buscar palpites
$palpites = dbFetchAll(
    "SELECT p.*, j.nome as jogador_nome 
     FROM palpites p 
     JOIN jogador j ON j.id = p.jogador_id 
     WHERE p.bolao_id = ? 
     ORDER BY p.data_palpite DESC", 
    [$bolaoId]
);

// Decodificar jogos para melhor visualização
$jogos = json_decode($bolao['jogos'], true);

// Função para calcular o resultado de um jogo
function calcularResultado($jogo) {
    if ($jogo['status'] !== 'FT') {
        return 'Não finalizado';
    }
    
    if ($jogo['resultado_casa'] == $jogo['resultado_visitante']) {
        return '0';
    }
    if ($jogo['resultado_casa'] > $jogo['resultado_visitante']) {
        return '1';
    }
    return '2';
}

// Função para mostrar o texto do resultado
function textoResultado($resultado) {
    switch ($resultado) {
        case '0': return '0 (Empate)';
        case '1': return '1 (Casa vence)';
        case '2': return '2 (Visitante vence)';
        default: return $resultado;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste JSON - Bolão <?= htmlspecialchars($bolao['nome']) ?></title>
    <meta charset="utf-8">
    <style>
        .acerto { color: green; font-weight: bold; }
        .erro { color: red; }
        pre { background: #f5f5f5; padding: 10px; }
    </style>
</head>
<body>
    <h1>Teste JSON - <?= htmlspecialchars($bolao['nome']) ?></h1>
    
    <h2>Jogos do Bolão</h2>
    <pre><?php
    foreach ($jogos as $jogo) {
        echo "ID: {$jogo['id']}\n";
        echo "Jogo: {$jogo['time_casa']} {$jogo['resultado_casa']} x {$jogo['resultado_visitante']} {$jogo['time_visitante']}\n";
        echo "Status: {$jogo['status']}\n";
        echo "Resultado: " . textoResultado(calcularResultado($jogo)) . "\n";
        echo "--------------------\n";
    }
    ?></pre>

    <h2>Palpites</h2>
    <?php foreach ($palpites as $palpite): ?>
        <h3><?= htmlspecialchars($palpite['jogador_nome']) ?> - <?= date('d/m/Y H:i', strtotime($palpite['data_palpite'])) ?></h3>
        <?php
        $palpitesJogos = json_decode($palpite['palpites'], true);
        if (isset($palpitesJogos['jogos'])):
            $totalAcertos = 0;
            $totalJogosFinalizados = 0;
        ?>
            <pre><?php
            foreach ($palpitesJogos['jogos'] as $jogoId => $resultado) {
                // Encontrar o jogo correspondente
                $jogoEncontrado = null;
                foreach ($jogos as $jogo) {
                    if ($jogo['id'] == $jogoId) {
                        $jogoEncontrado = $jogo;
                        break;
                    }
                }

                echo "ID do Jogo: {$jogoId}\n";
                if ($jogoEncontrado) {
                    echo "Jogo: {$jogoEncontrado['time_casa']} {$jogoEncontrado['resultado_casa']} x {$jogoEncontrado['resultado_visitante']} {$jogoEncontrado['time_visitante']}\n";
                    echo "Status: {$jogoEncontrado['status']}\n";
                    
                    if ($jogoEncontrado['status'] === 'FT') {
                        $totalJogosFinalizados++;
                        $resultadoReal = calcularResultado($jogoEncontrado);
                        $acertou = $resultado === $resultadoReal;
                        if ($acertou) $totalAcertos++;
                        
                        echo "Resultado Real: " . textoResultado($resultadoReal) . "\n";
                        echo "Palpite: " . textoResultado($resultado) . "\n";
                        echo $acertou ? "✅ ACERTOU!\n" : "❌ ERROU!\n";
                    } else {
                        echo "Palpite: " . textoResultado($resultado) . "\n";
                    }
                } else {
                    echo "Jogo não encontrado\n";
                    echo "Palpite: " . textoResultado($resultado) . "\n";
                }
                echo "--------------------\n";
            }
            
            if ($totalJogosFinalizados > 0) {
                echo "\nRESUMO:\n";
                echo "Total de jogos finalizados: {$totalJogosFinalizados}\n";
                echo "Total de acertos: {$totalAcertos}\n";
                echo "Aproveitamento: " . number_format(($totalAcertos / $totalJogosFinalizados) * 100, 1) . "%\n";
            }
            ?></pre>
        <?php else: ?>
            <p>Formato de palpite inválido</p>
        <?php endif; ?>
    <?php endforeach; ?>

    <p><a href="<?= APP_URL ?>/admin/palpites-bolao.php?bolao_id=<?= $bolaoId ?>">Voltar para Palpites</a></p>
</body>
</html> 