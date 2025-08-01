<?php
/**
 * Admin Ação Bolão - Bolão Vitimba
 * Handles bolão-related actions
 */
require_once '../config/config.php';require_once '../includes/functions.php';

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
$bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ?", [$bolaoId]);

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
            // Delete participacoes and rankings (jogos are stored as JSON, no need to delete separately)
            dbDelete('participacoes', 'bolao_id = ?', [$bolaoId]);
            dbDelete('rankings', 'bolao_id = ?', [$bolaoId]);
            
            // Delete bolão from dados_boloes
            $bolaoDeleted = dbDelete('dados_boloes', 'id = ?', [$bolaoId]);
            
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
        // Calculate points for all palpites in the bolão using JSON data
        $jogosJson = json_decode($bolao['jogos'], true) ?? [];
        
        // Filter only finalized games with results
        $jogosFinalizados = array_filter($jogosJson, function($jogo) {
            return $jogo['status'] === 'FT' && 
                   isset($jogo['resultado_casa']) && 
                   isset($jogo['resultado_visitante']) &&
                   $jogo['resultado_casa'] !== null &&
                   $jogo['resultado_visitante'] !== null;
        });
        
        $updated = 0;
        
        foreach ($jogosFinalizados as $jogo) {
            // Get all palpites for this bolão
            $palpites = dbFetchAll(
                "SELECT id, palpites FROM palpites WHERE bolao_id = ? AND status = 'pago'", 
                [$bolaoId]
            );
            
            foreach ($palpites as $palpiteRow) {
                $palpitesData = json_decode($palpiteRow['palpites'], true) ?? [];
                $pontosTotais = 0;
                $palpitesAtualizados = [];
                
                // Process each palpite in the JSON
                foreach ($palpitesData as $palpiteKey => $palpiteValue) {
                    // Remove 'resultado_' prefix if present
                    $jogoId = str_replace('resultado_', '', $palpiteKey);
                    
                    // Check if this palpite is for the current game
                    if ($jogoId == $jogo['id']) {
                        // Parse palpite (format: "2x1")
                        if (preg_match('/^(\d+)x(\d+)$/', $palpiteValue, $matches)) {
                            $palpiteCasa = (int)$matches[1];
                            $palpiteVisitante = (int)$matches[2];
                            
                            // Calculate points (you'll need to implement this function)
                            $pontos = calculateMatchPoints(
                                $palpiteCasa,
                                $palpiteVisitante,
                                (int)$jogo['resultado_casa'],
                                (int)$jogo['resultado_visitante']
                            );
                            
                            $pontosTotais += $pontos;
                            $palpitesAtualizados[$palpiteKey] = $palpiteValue;
                        }
                    } else {
                        $palpitesAtualizados[$palpiteKey] = $palpiteValue;
                    }
                }
                
                // Update palpite with calculated points
                $result = dbUpdate(
                    'palpites',
                    ['palpites' => json_encode($palpitesAtualizados, JSON_UNESCAPED_UNICODE)],
                    ['id' => $palpiteRow['id']]
                );
                
                if ($result) {
                    $updated++;
                }
            }
        }
        
        setFlashMessage('success', "Pontuação calculada com sucesso! $updated palpites atualizados.");
        break;
        
    default:
        setFlashMessage('danger', 'Ação inválida.');
        break;
}

// Redirect back to bolão page
redirect(APP_URL . '/admin/bolao.php?id=' . $bolaoId); 