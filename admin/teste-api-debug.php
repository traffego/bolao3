<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Teste direto da API
echo "<h2>Teste da API Football</h2>";

// Verificar configuração
$config = getConfig('api_football');
echo "<h3>Configuração da API:</h3>";
echo "<pre>";
print_r($config);
echo "</pre>";

// Teste de busca de jogos
echo "<h3>Teste de Busca de Jogos - Brasileirão Série A (ID: 71):</h3>";

$params = [
    'league' => 71,
    'season' => 2024,
    'from' => '2024-01-01',
    'to' => '2024-12-31'
];

echo "<p>Parâmetros: " . json_encode($params) . "</p>";

$resultado = fetchApiFootballData('fixtures', $params);

echo "<p>Resultado da API:</p>";
echo "<pre>";
if ($resultado) {
    echo "Total de jogos encontrados: " . count($resultado) . "\n";
    if (count($resultado) > 0) {
        echo "Primeiro jogo:\n";
        print_r($resultado[0]);
    }
} else {
    echo "Nenhum resultado retornado ou erro na API";
}
echo "</pre>";

// Teste com outros campeonatos
echo "<h3>Teste - Copa do Brasil (ID: 73):</h3>";
$params['league'] = 73;
$resultado2 = fetchApiFootballData('fixtures', $params);
echo "<p>Jogos encontrados: " . (is_array($resultado2) ? count($resultado2) : 'Erro') . "</p>";

// Teste com Libertadores
echo "<h3>Teste - Libertadores (ID: 13):</h3>";
$params['league'] = 13;
$resultado3 = fetchApiFootballData('fixtures', $params);
echo "<p>Jogos encontrados: " . (is_array($resultado3) ? count($resultado3) : 'Erro') . "</p>";

?>