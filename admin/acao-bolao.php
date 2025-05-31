<?php
/**
 * Admin Ação Bolão - Bolão Football
 * Handles bolão-related actions
 */
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Por favor, faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('danger', 'Método de requisição inválido.');
    redirect(APP_URL . '/admin/');
}

// Get action and bolão ID
$action = $_POST['action'] ?? '';
$bolaoId = isset($_POST['bolao_id']) ? (int)$_POST['bolao_id'] : 0;

// Validate bolão ID
if ($bolaoId <= 0) {
    setFlashMessage('danger', 'ID do bolão inválido.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Check if bolão exists
$bolao = dbFetchOne("SELECT * FROM boloes WHERE id = ?", [$bolaoId]);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Process action
switch ($action) {
    case 'open':
        // Update bolão status to open
        $result = dbUpdate('boloes', ['status' => 'aberto'], 'id = ?', [$bolaoId]);
        
        if ($result) {
            setFlashMessage('success', 'Bolão reaberto com sucesso!');
        } else {
            setFlashMessage('danger', 'Erro ao reabrir o bolão.');
        }
        break;
        
    case 'close':
        // Update bolão status to closed
        $result = dbUpdate('boloes', ['status' => 'fechado'], 'id = ?', [$bolaoId]);
        
        if ($result) {
            setFlashMessage('success', 'Bolão fechado com sucesso!');
        } else {
            setFlashMessage('danger', 'Erro ao fechar o bolão.');
        }
        break;
        
    case 'finish':
        // Update bolão status to finished
        $result = dbUpdate('boloes', ['status' => 'finalizado'], 'id = ?', [$bolaoId]);
        
        if ($result) {
            // Update rankings if needed
            updateRanking($bolaoId);
            
            setFlashMessage('success', 'Bolão finalizado com sucesso!');
        } else {
            setFlashMessage('danger', 'Erro ao finalizar o bolão.');
        }
        break;
        
    case 'remove_jogador':
        // Get jogador ID
        $jogadorId = isset($_POST['jogador_id']) ? (int)$_POST['jogador_id'] : 0;
        
        if ($jogadorId <= 0) {
            setFlashMessage('danger', 'ID do jogador inválido.');
            redirect(APP_URL . '/admin/bolao.php?id=' . $bolaoId);
        }
        
        // Start transaction
        dbBeginTransaction();
        
        try {
            // Delete palpites
            $palpitesDeleted = dbDelete('palpites', 'jogador_id = ? AND bolao_id = ?', [$jogadorId, $bolaoId]);
            
            // Delete participation
            $participacaoDeleted = dbDelete('participacoes', 'jogador_id = ? AND bolao_id = ?', [$jogadorId, $bolaoId]);
            
            // Delete from rankings if exists
            $rankingDeleted = dbDelete('rankings', 'jogador_id = ? AND bolao_id = ?', [$jogadorId, $bolaoId]);
            
            // Commit transaction
            dbCommit();
            
            setFlashMessage('success', 'Jogador removido do bolão com sucesso!');
        } catch (Exception $e) {
            // Rollback in case of error
            dbRollback();
            setFlashMessage('danger', 'Erro ao remover jogador do bolão: ' . $e->getMessage());
        }
        break;
        
    case 'delete':
        // Check if there are palpites for this bolão
        $palpitesCount = dbCount('palpites', 'bolao_id = ?', [$bolaoId]);
        
        if ($palpitesCount > 0) {
            setFlashMessage('danger', 'Não é possível excluir um bolão com palpites. Remova todos os palpites primeiro.');
            redirect(APP_URL . '/admin/bolao.php?id=' . $bolaoId);
        }
        
        // Start transaction
        dbBeginTransaction();
        
        try {
            // Delete jogos and resultados
            $jogos = dbFetchAll("SELECT id FROM jogos WHERE bolao_id = ?", [$bolaoId]);
            
            foreach ($jogos as $jogo) {
                dbDelete('resultados', 'jogo_id = ?', [$jogo['id']]);
            }
            
            dbDelete('jogos', 'bolao_id = ?', [$bolaoId]);
            
            // Delete participacoes and rankings
            dbDelete('participacoes', 'bolao_id = ?', [$bolaoId]);
            dbDelete('rankings', 'bolao_id = ?', [$bolaoId]);
            
            // Delete bolão
            $bolaoDeleted = dbDelete('boloes', 'id = ?', [$bolaoId]);
            
            // Commit transaction
            dbCommit();
            
            if ($bolaoDeleted) {
                setFlashMessage('success', 'Bolão excluído com sucesso!');
                redirect(APP_URL . '/admin/boloes.php');
            } else {
                setFlashMessage('danger', 'Erro ao excluir o bolão.');
            }
        } catch (Exception $e) {
            // Rollback in case of error
            dbRollback();
            setFlashMessage('danger', 'Erro ao excluir bolão: ' . $e->getMessage());
        }
        break;
        
    case 'calculate_points':
        // Calculate points for all palpites in the bolão
        $jogos = dbFetchAll(
            "SELECT j.id, r.gols_casa, r.gols_visitante 
             FROM jogos j
             JOIN resultados r ON r.jogo_id = j.id
             WHERE j.bolao_id = ? AND j.status = 'finalizado'", 
            [$bolaoId]
        );
        
        $updated = 0;
        
        foreach ($jogos as $jogo) {
            // Get all palpites for this game
            $palpites = dbFetchAll(
                "SELECT id, gols_casa, gols_visitante 
                 FROM palpites 
                 WHERE jogo_id = ?", 
                [$jogo['id']]
            );
            
            foreach ($palpites as $palpite) {
                // Calculate points
                $points = calculatePoints(
                    $palpite['gols_casa'], 
                    $palpite['gols_visitante'], 
                    $jogo['gols_casa'], 
                    $jogo['gols_visitante']
                );
                
                // Update palpite
                $result = dbUpdate(
                    'palpites', 
                    ['pontos' => $points], 
                    'id = ?', 
                    [$palpite['id']]
                );
                
                if ($result) {
                    $updated++;
                }
            }
        }
        
        // Update rankings
        updateRanking($bolaoId);
        
        setFlashMessage('success', "Pontuação calculada com sucesso! $updated palpites atualizados.");
        break;
        
    default:
        setFlashMessage('danger', 'Ação inválida.');
        break;
}

// Redirect back to bolão page
redirect(APP_URL . '/admin/bolao.php?id=' . $bolaoId); 