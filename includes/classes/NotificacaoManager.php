<?php
class NotificacaoManager {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Cria uma nova notificação
     */
    public function criar($jogadorId, $tipo, $titulo, $mensagem, $dadosAdicionais = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notificacoes (
                    jogador_id, 
                    tipo, 
                    titulo, 
                    mensagem, 
                    dados_adicionais
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $jogadorId,
                $tipo,
                $titulo,
                $mensagem,
                $dadosAdicionais ? json_encode($dadosAdicionais) : null
            ]);
            
        } catch (Exception $e) {
            error_log('Erro ao criar notificação: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca notificações de um jogador
     */
    public function buscarPorJogador($jogadorId, $apenasNaoLidas = false, $limit = 10) {
        try {
            $sql = "
                SELECT * 
                FROM notificacoes 
                WHERE jogador_id = ?
            ";
            
            if ($apenasNaoLidas) {
                $sql .= " AND lida = 0";
            }
            
            $sql .= " ORDER BY data_criacao DESC LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$jogadorId, $limit]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log('Erro ao buscar notificações: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Marca uma notificação como lida
     */
    public function marcarComoLida($notificacaoId, $jogadorId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notificacoes 
                SET lida = 1,
                    data_leitura = NOW()
                WHERE id = ? 
                AND jogador_id = ?
            ");
            
            return $stmt->execute([$notificacaoId, $jogadorId]);
            
        } catch (Exception $e) {
            error_log('Erro ao marcar notificação como lida: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marca todas as notificações do jogador como lidas
     */
    public function marcarTodasComoLidas($jogadorId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notificacoes 
                SET lida = 1,
                    data_leitura = NOW()
                WHERE jogador_id = ? 
                AND lida = 0
            ");
            
            return $stmt->execute([$jogadorId]);
            
        } catch (Exception $e) {
            error_log('Erro ao marcar notificações como lidas: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Conta notificações não lidas
     */
    public function contarNaoLidas($jogadorId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM notificacoes 
                WHERE jogador_id = ? 
                AND lida = 0
            ");
            
            $stmt->execute([$jogadorId]);
            return (int) $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log('Erro ao contar notificações: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Notifica sobre aprovação de saque
     */
    public function notificarSaqueAprovado($jogadorId, $valor, $dadosAdicionais = []) {
        $titulo = "Saque Aprovado";
        $mensagem = "Seu saque no valor de " . formatMoney($valor) . " foi aprovado e processado com sucesso.";
        
        return $this->criar(
            $jogadorId,
            'saque_aprovado',
            $titulo,
            $mensagem,
            $dadosAdicionais
        );
    }
    
    /**
     * Notifica sobre rejeição de saque
     */
    public function notificarSaqueRejeitado($jogadorId, $valor, $motivo, $dadosAdicionais = []) {
        $titulo = "Saque Rejeitado";
        $mensagem = "Seu saque no valor de " . formatMoney($valor) . " foi rejeitado.\n\n";
        $mensagem .= "Motivo: " . $motivo;
        
        return $this->criar(
            $jogadorId,
            'saque_rejeitado',
            $titulo,
            $mensagem,
            $dadosAdicionais
        );
    }
} 