<?php
/**
 * Debug temporário para analisar problema dos acertos
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

// Get the most recent bolão automatically
$bolao = dbFetchOne("SELECT * FROM dados_boloes ORDER BY data_inicio DESC LIMIT 1");
$bolaoId = $bolao ? $bolao['id'] : 0;

echo "<h1>Debug Acertos - Bolão: {$bolao['nome']}</h1>";

// Function to calculate resultado from game data
function calcularResultado($jogo) {
    if (!isset($jogo['resultado_casa']) || !isset($jogo['resultado_visitante'])) {
        return null;
    }
    
    $casa = (int)$jogo['resultado_casa'];
    $visitante = (int)$jogo['resultado_visitante'];
    
    if ($casa > $visitante) {
        return '1'; // Vitória casa
    } elseif ($visitante > $casa) {
        return '2'; // Vitória visitante
    } else {
        return '0'; // Empate
    }
}

if ($bolao) {
    // Get jogos from bolão JSON
    $jogosJson = json_decode($bolao['jogos'], true) ?? [];
    
    echo "<h2>Jogos do Bolão (" . count($jogosJson) . " jogos)</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Jogo</th><th>Status</th><th>Resultado Casa</th><th>Resultado Visitante</th><th>Resultado Calculado</th></tr>";
    
    foreach ($jogosJson as $jogo) {
        $resultadoCalculado = calcularResultado($jogo);
        echo "<tr>";
        echo "<td>{$jogo['id']}</td>";
        echo "<td>{$jogo['time_casa']} x {$jogo['time_visitante']}</td>";
        echo "<td>{$jogo['status']}</td>";
        echo "<td>" . ($jogo['resultado_casa'] ?? 'null') . "</td>";
        echo "<td>" . ($jogo['resultado_visitante'] ?? 'null') . "</td>";
        echo "<td>" . ($resultadoCalculado ?? 'null') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Get all palpites for this bolão
    $palpites = dbFetchAll("
        SELECT p.*, j.nome as nome_jogador, j.email as email_jogador 
        FROM palpites p 
        LEFT JOIN jogador j ON p.jogador_id = j.id 
        WHERE p.bolao_id = ? AND p.status = 'pago'
        ORDER BY p.data_palpite DESC
    ", [$bolaoId]);
    
    echo "<h2>Palpites (" . count($palpites) . " palpites)</h2>";
    
    foreach ($palpites as $palpite) {
        echo "<h3>Jogador: {$palpite['nome_jogador']} (ID: {$palpite['jogador_id']})</h3>";
        
        // Decode palpites JSON
        $palpitesJson = json_decode($palpite['palpites'], true);
        
        echo "<h4>JSON Raw:</h4>";
        echo "<pre>" . htmlspecialchars($palpite['palpites']) . "</pre>";
        
        echo "<h4>JSON Decodificado:</h4>";
        echo "<pre>" . print_r($palpitesJson, true) . "</pre>";
        
        if ($palpitesJson && isset($palpitesJson['jogos']) && $jogosJson) {
            echo "<h4>Análise dos Acertos:</h4>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Jogo ID</th><th>Jogo</th><th>Status</th><th>Palpite</th><th>Resultado Real</th><th>Acertou?</th><th>Tipos</th></tr>";
            
            $totalAcertos = 0;
            $totalJogosFinalizados = 0;
            
            foreach ($jogosJson as $jogo) {
                if (isset($palpitesJson['jogos'][$jogo['id']])) {
                    $palpiteJogador = $palpitesJson['jogos'][$jogo['id']];
                    $resultadoReal = calcularResultado($jogo);
                    
                    echo "<tr>";
                    echo "<td>{$jogo['id']}</td>";
                    echo "<td>{$jogo['time_casa']} x {$jogo['time_visitante']}</td>";
                    echo "<td>{$jogo['status']}</td>";
                    echo "<td>" . htmlspecialchars($palpiteJogador) . "</td>";
                    echo "<td>" . ($resultadoReal ?? 'null') . "</td>";
                    
                    if ($jogo['status'] === 'FT') {
                        $totalJogosFinalizados++;
                        $acertou = ($resultadoReal !== null && $palpiteJogador === $resultadoReal);
                        if ($acertou) $totalAcertos++;
                        
                        echo "<td style='color: " . ($acertou ? 'green' : 'red') . "'>" . ($acertou ? 'SIM' : 'NÃO') . "</td>";
                        echo "<td>Palpite: " . gettype($palpiteJogador) . " | Real: " . gettype($resultadoReal) . "</td>";
                    } else {
                        echo "<td>-</td>";
                        echo "<td>Jogo não finalizado</td>";
                    }
                    echo "</tr>";
                }
            }
            echo "</table>";
            
            echo "<p><strong>Total de acertos: $totalAcertos de $totalJogosFinalizados jogos finalizados</strong></p>";
            echo "<hr>";
        }
    }
} else {
    echo "<p>Nenhum bolão encontrado.</p>";
}
?>