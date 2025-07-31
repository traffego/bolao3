<?php
/**
 * Script para apagar arquivos não utilizados no sistema Bolão
 * Este script identifica e remove arquivos que não estão sendo usados diretamente pelo sistema
 */

// Definir o diretório base do projeto
$baseDir = __DIR__;

// Lista de arquivos e diretórios a serem excluídos
$arquivosInuteis = [
    // Arquivos SQL de backup reais encontrados
    'BKPdobanco.sql',
    'BKPdobanco2.sql', 
    'BKPdobanco3.sql',
    'BKPdobanco_migrando.sql',
    'bkp1.sql',
    'configs.sql',
    'estruturadobanco.sql',
    
    // Arquivos de teste
    'test_database.php',
    'test_db_prepare.php',
    'test_efi.php',
    'test_multiple_db.php',
    'test_php_version.php',
    'test_server.php',
    'test_urls.php',
    'test_urls_simple.php',
    'check_database.php',
    'check_db_prepare.php',
    'debug.php',
    'cleanup.php',
    'auto_fix.php',
    'import_backup.php',
    'database_structure.php',
    
    // Arquivos de documentação
    'api_football_documentation.txt',
    'docs_api_football.txt',
    'cursor_leia_o_arquivo_tasks_md_13e58_23Julho.md',
    'tasks_public.md',
    'TASKS.md',
    
    // Arquivos PDF
    'bolao1.pdf',
    
    // Certificados de teste
    'certifu.p12',
    
    // Diretório SQL (se não estiver sendo usado)
    'sql/',
    
    // Diretório database (scripts de instalação/migração)
    'database/',
    
    // Cache (pode ser regenerado)
    'cache/',
];

// Função para remover diretórios recursivamente
function removerDiretorio($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            removerDiretorio($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// Função para verificar se um arquivo está em uso
function estaEmUso($arquivo) {
    // Lista de arquivos que são essenciais para o sistema
    $arquivosEssenciais = [
        'config.php',
        'index.php',
        'bolao.php',
        'admin/',
        'api/',
        'includes/',
        'templates/',
        'assets/',
        'css/',
        'js/',
        'database/',
        'cron/',
        'ajax/'
    ];
    
    foreach ($arquivosEssenciais as $essencial) {
        if (strpos($arquivo, $essencial) === 0) {
            return true;
        }
    }
    
    return false;
}

// Verificar se o script está sendo executado no navegador
if (php_sapi_name() !== 'cli') {
    echo "<h1>Script de Limpeza de Arquivos Inúteis</h1>";
    echo "<p>Este script irá remover arquivos que não estão sendo utilizados diretamente pelo sistema.</p>";
    
    if (isset($_GET['confirmar']) && $_GET['confirmar'] === '1') {
        echo "<h2>Processo de Limpeza Iniciado</h2>";
        echo "<ul>";
        
        $arquivosRemovidos = 0;
        
        foreach ($arquivosInuteis as $arquivo) {
            $caminhoCompleto = $baseDir . DIRECTORY_SEPARATOR . $arquivo;
            
            if (file_exists($caminhoCompleto)) {
                if (is_dir($caminhoCompleto)) {
                    if (removerDiretorio($caminhoCompleto)) {
                        echo "<li>Diretório removido: <strong>$arquivo</strong></li>";
                        $arquivosRemovidos++;
                    } else {
                        echo "<li style='color: red;'>Falha ao remover diretório: <strong>$arquivo</strong></li>";
                    }
                } else {
                    if (unlink($caminhoCompleto)) {
                        echo "<li>Arquivo removido: <strong>$arquivo</strong></li>";
                        $arquivosRemovidos++;
                    } else {
                        echo "<li style='color: red;'>Falha ao remover arquivo: <strong>$arquivo</strong></li>";
                    }
                }
            } else {
                echo "<li style='color: gray;'>Arquivo/diretório não encontrado: <strong>$arquivo</strong></li>";
            }
        }
        
        echo "</ul>";
        echo "<p><strong>Total de arquivos/diretórios removidos: $arquivosRemovidos</strong></p>";
        echo "<p style='color: green;'><strong>Processo de limpeza concluído!</strong></p>";
    } else {
        echo "<p>Os seguintes arquivos e diretórios serão removidos:</p>";
        echo "<ul>";
        foreach ($arquivosInuteis as $arquivo) {
            echo "<li>$arquivo</li>";
        }
        echo "</ul>";
        echo "<p><a href='?confirmar=1' style='background-color: red; color: white; padding: 10px; text-decoration: none; font-weight: bold;'>CONFIRMAR EXCLUSÃO</a></p>";
        echo "<p style='color: red;'><strong>ATENÇÃO: Esta ação não pode ser desfeita!</strong></p>";
    }
} else {
    echo "Este script deve ser executado através do navegador, não pelo terminal.\n";
    echo "Acesse: http://seuservidor/apagarinuteis.php\n";
}
?>
