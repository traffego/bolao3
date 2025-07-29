<?php
// Script para tentar corrigir automaticamente problemas comuns de conexão

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🛠️ AUTO-DIAGNÓSTICO E CORREÇÃO</h1>";

// Função para testar conexão
function testarConexao($host, $user, $pass, $dbname = null, $options = []) {
    try {
        $dsn = "mysql:host=$host" . ($dbname ? ";dbname=$dbname" : "") . ";charset=utf8mb4";
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10
        ];
        $pdo = new PDO($dsn, $user, $pass, array_merge($defaultOptions, $options));
        return ['sucesso' => true, 'pdo' => $pdo, 'erro' => null];
    } catch (PDOException $e) {
        return ['sucesso' => false, 'pdo' => null, 'erro' => $e->getMessage()];
    }
}

// Credenciais
$host = 'localhost';
$user = 'platafo5_bolao3';
$pass = 'Traffego444#';
$dbname = 'platafo5_bolao3';

echo "<h2>🔍 Passo 1: Teste Básico de Conexão</h2>";

// Primeiro, testar conexão sem especificar banco
$resultado = testarConexao($host, $user, $pass);

if ($resultado['sucesso']) {
    echo "<p>✅ <strong>Conexão MySQL OK!</strong></p>";
    
    // Listar bancos
    $pdo = $resultado['pdo'];
    $stmt = $pdo->query("SHOW DATABASES");
    $bancos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>📋 Bancos Disponíveis:</h3>";
    $bancoExiste = false;
    foreach ($bancos as $banco) {
        $destaque = ($banco === $dbname) ? " ⭐ <strong>(ALVO)</strong>" : "";
        echo "<p>• $banco$destaque</p>";
        if ($banco === $dbname) $bancoExiste = true;
    }
    
    if (!$bancoExiste) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
        echo "<h3>⚠️ PROBLEMA ENCONTRADO</h3>";
        echo "<p>O banco <strong>'$dbname'</strong> não existe!</p>";
        echo "<p><strong>Soluções:</strong></p>";
        echo "<ol>";
        echo "<li>Criar o banco no cPanel/phpMyAdmin</li>";
        echo "<li>Verificar se o nome está correto</li>";
        echo "<li>Importar backup do banco</li>";
        echo "</ol>";
        echo "</div>";
        
        // Tentar criar o banco (se tiver permissão)
        echo "<h3>🛠️ Tentativa de Criação Automática:</h3>";
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "<p>✅ <strong>Banco criado com sucesso!</strong></p>";
            $bancoExiste = true;
        } catch (PDOException $e) {
            echo "<p>❌ <strong>Não foi possível criar o banco:</strong> " . $e->getMessage() . "</p>";
            echo "<p>Você precisará criar manualmente via cPanel.</p>";
        }
    } else {
        echo "<p>✅ <strong>Banco '$dbname' encontrado!</strong></p>";
    }
    
    // Se o banco existe, testar conexão específica
    if ($bancoExiste) {
        echo "<h2>🎯 Passo 2: Teste Conexão com Banco Específico</h2>";
        $resultado2 = testarConexao($host, $user, $pass, $dbname);
        
        if ($resultado2['sucesso']) {
            echo "<p>✅ <strong>Conexão com banco específico OK!</strong></p>";
            
            $pdo2 = $resultado2['pdo'];
            
            // Verificar tabelas
            $stmt = $pdo2->query("SHOW TABLES");
            $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<h3>📊 Status das Tabelas:</h3>";
            if (count($tabelas) > 0) {
                echo "<p>✅ <strong>Banco tem " . count($tabelas) . " tabelas</strong></p>";
                echo "<details><summary>Ver tabelas</summary><ul>";
                foreach ($tabelas as $tabela) {
                    echo "<li>$tabela</li>";
                }
                echo "</ul></details>";
                
                // Testar query básica numa tabela conhecida
                try {
                    $stmt = $pdo2->query("SELECT COUNT(*) as total FROM dados_boloes");
                    $result = $stmt->fetch();
                    echo "<p>✅ <strong>Query teste OK:</strong> {$result['total']} bolões encontrados</p>";
                    
                    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
                    echo "<h3>🎉 BANCO FUNCIONANDO PERFEITAMENTE!</h3>";
                    echo "<p>O erro 500 não é causado pelo banco de dados.</p>";
                    echo "<p><strong>Próximo passo:</strong> Verificar outros arquivos PHP.</p>";
                    echo "</div>";
                    
                } catch (PDOException $e) {
                    echo "<p>⚠️ <strong>Tabela 'dados_boloes' com problema:</strong> " . $e->getMessage() . "</p>";
                }
                
            } else {
                echo "<p>⚠️ <strong>Banco vazio - sem tabelas</strong></p>";
                echo "<p>Você precisa importar a estrutura do banco.</p>";
                
                // Sugerir arquivos SQL disponíveis
                $sqlFiles = glob(__DIR__ . '/*.sql');
                if (count($sqlFiles) > 0) {
                    echo "<p><strong>Arquivos SQL encontrados para importar:</strong></p>";
                    echo "<ul>";
                    foreach ($sqlFiles as $file) {
                        $filename = basename($file);
                        echo "<li>$filename</li>";
                    }
                    echo "</ul>";
                }
            }
            
        } else {
            echo "<p>❌ <strong>Falha na conexão específica:</strong> " . $resultado2['erro'] . "</p>";
        }
    }
    
} else {
    echo "<p>❌ <strong>Falha na conexão básica:</strong> " . $resultado['erro'] . "</p>";
    
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h3>🚨 ERRO CRÍTICO DE CONEXÃO</h3>";
    echo "<p><strong>Possíveis causas:</strong></p>";
    echo "<ul>";
    echo "<li>Credenciais incorretas</li>";
    echo "<li>MySQL não está rodando</li>";
    echo "<li>Usuário não tem permissões</li>";
    echo "<li>Configuração de firewall</li>";
    echo "</ul>";
    echo "</div>";
}

// Informações finais
echo "<h2>📋 Resumo da Configuração Atual</h2>";
echo "<p><strong>Host:</strong> $host</p>";
echo "<p><strong>Usuário:</strong> $user</p>";
echo "<p><strong>Banco:</strong> $dbname</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

echo "<h2>✅ Próximos Passos Recomendados</h2>";
echo "<ol>";
echo "<li><strong>Se conexão falhou:</strong> Verificar credenciais no cPanel</li>";
echo "<li><strong>Se banco não existe:</strong> Criar via cPanel</li>";
echo "<li><strong>Se banco vazio:</strong> Importar arquivo SQL</li>";
echo "<li><strong>Se tudo OK:</strong> O problema não é no banco - verificar outros arquivos</li>";
echo "</ol>";
?>
