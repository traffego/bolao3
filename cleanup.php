<?php
// Script de limpeza do sistema
date_default_timezone_set('America/Sao_Paulo');

// Criar pasta de backup se não existir
$backupDir = 'backup_' . date('Y-m-d_H-i-s');
if (!file_exists($backupDir)) {
    mkdir($backupDir);
}

// Função para fazer backup do arquivo antes de deletar
function backupFile($file, $backupDir) {
    if (file_exists($file)) {
        copy($file, $backupDir . '/' . basename($file));
        return true;
    }
    return false;
}

// Função para encontrar o arquivo SQL mais recente
function getMostRecentSql() {
    $sqlFiles = glob('*.sql');
    $latest = ['file' => '', 'time' => 0];
    
    foreach ($sqlFiles as $file) {
        $mtime = filemtime($file);
        if ($mtime > $latest['time']) {
            $latest = ['file' => $file, 'time' => $mtime];
        }
    }
    
    return $latest['file'];
}

// Array de arquivos de teste para remover
$testFiles = [
    'test_db_config.php',
    'test_pix_config.php',
    'test_efi_config.php',
    'check_tables.php'
];

// Log das operações
$log = [];

// Processar arquivos SQL
$mostRecentSql = getMostRecentSql();
$sqlFiles = glob('*.sql');

echo "Iniciando processo de limpeza...\n\n";
echo "Pasta de backup criada: $backupDir\n\n";

// Remover SQLs antigos
foreach ($sqlFiles as $sqlFile) {
    if ($sqlFile !== $mostRecentSql) {
        if (backupFile($sqlFile, $backupDir)) {
            if (unlink($sqlFile)) {
                $log[] = "SQL antigo removido com sucesso: $sqlFile";
            } else {
                $log[] = "ERRO ao remover SQL: $sqlFile";
            }
        }
    } else {
        $log[] = "SQL mantido (mais recente): $sqlFile";
    }
}

// Remover arquivos de teste
foreach ($testFiles as $testFile) {
    if (file_exists($testFile)) {
        if (backupFile($testFile, $backupDir)) {
            if (unlink($testFile)) {
                $log[] = "Arquivo de teste removido com sucesso: $testFile";
            } else {
                $log[] = "ERRO ao remover arquivo de teste: $testFile";
            }
        }
    } else {
        $log[] = "Arquivo de teste não encontrado: $testFile";
    }
}

// Exibir log das operações
echo "\nLog das operações:\n";
echo "==================\n";
foreach ($log as $entry) {
    echo $entry . "\n";
}

echo "\nProcesso de limpeza concluído!\n";
echo "Todos os arquivos removidos foram copiados para a pasta: $backupDir\n";
?> 