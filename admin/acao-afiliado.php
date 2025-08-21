<?php
/**
 * Processamento de ações relacionadas aos afiliados
 */
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Verificar autenticação do admin
if (!isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

// Verificar token CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setError('Token de segurança inválido');
        redirect(APP_URL . '/admin/afiliados.php');
    }
}

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Ação inválida'];

switch ($action) {
    case 'create':
    case 'update':
        // Validar dados
        $required_fields = ['nome', 'email', 'comissao_percentual'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                setError("O campo {$field} é obrigatório");
                redirect(APP_URL . '/admin/afiliados.php');
            }
        }

        $data = [
            'nome' => trim($_POST['nome']),
            'email' => trim($_POST['email']),
            'telefone' => trim($_POST['telefone'] ?? ''),
            'comissao_percentual' => (float)$_POST['comissao_percentual'],
            'pix_chave' => trim($_POST['pix_chave'] ?? ''),
            'pix_tipo' => $_POST['pix_tipo'] ?? null,
            'status' => $_POST['status'] ?? 'ativo'
        ];

        // Validar email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            setError('Email inválido');
            redirect(APP_URL . '/admin/afiliados.php');
        }

        // Validar comissão
        if ($data['comissao_percentual'] < 0 || $data['comissao_percentual'] > 100) {
            setError('Percentual de comissão inválido');
            redirect(APP_URL . '/admin/afiliados.php');
        }

        try {
            if ($action === 'create') {
                // Gerar código único de afiliado
                do {
                    $codigo = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
                } while (dbFetchOne("SELECT id FROM afiliados WHERE codigo_afiliado = ?", [$codigo]));
                
                $data['codigo_afiliado'] = $codigo;
                
                // Inserir novo afiliado
                $id = dbInsert('afiliados', $data);
                if ($id) {
                    logAdminAction('criar_afiliado', "Afiliado {$data['nome']} criado", $data);
                    setSuccess('Afiliado criado com sucesso');
                }
            } else {
                // Atualizar afiliado existente
                $id = (int)$_POST['id'];
                if (!$id) {
                    throw new Exception('ID do afiliado inválido');
                }

                $success = dbUpdate('afiliados', $data, 'id = ?', [$id]);
                if ($success) {
                    logAdminAction('atualizar_afiliado', "Afiliado {$data['nome']} atualizado", $data);
                    setSuccess('Afiliado atualizado com sucesso');
                }
            }
        } catch (Exception $e) {
            setError('Erro ao salvar afiliado: ' . $e->getMessage());
        }
        break;

    case 'delete':
        $id = (int)$_POST['afiliado_id'];
        if (!$id) {
            setError('ID do afiliado inválido');
            redirect(APP_URL . '/admin/afiliados.php');
        }

        // Verificar se existem comissões pendentes
        $comissoesPendentes = dbFetchOne(
            "SELECT COUNT(*) as total FROM afiliados_comissoes 
             WHERE afiliado_id = ? AND status = 'pendente'",
            [$id]
        );

        if ($comissoesPendentes['total'] > 0) {
            setError('Não é possível excluir um afiliado com comissões pendentes');
            redirect(APP_URL . '/admin/afiliados.php');
        }

        try {
            // Buscar dados do afiliado para o log
            $afiliado = dbFetchOne("SELECT * FROM afiliados WHERE id = ?", [$id]);
            if (!$afiliado) {
                throw new Exception('Afiliado não encontrado');
            }

            // Iniciar transação
            dbBeginTransaction();

            // Excluir registros relacionados
            dbDelete('afiliados_indicacoes', 'afiliado_id = ?', [$id]);
            dbDelete('afiliados_comissoes', 'afiliado_id = ?', [$id]);
            dbDelete('afiliados', 'id = ?', [$id]);

            // Confirmar transação
            dbCommit();

            logAdminAction('excluir_afiliado', "Afiliado {$afiliado['nome']} excluído", $afiliado);
            setSuccess('Afiliado excluído com sucesso');
        } catch (Exception $e) {
            dbRollback();
            setError('Erro ao excluir afiliado: ' . $e->getMessage());
        }
        break;

    case 'toggle_status':
        $jogador_id = (int)$_POST['jogador_id'];
        if (!$jogador_id) {
            setError('ID do jogador inválido');
            redirect(APP_URL . '/admin/afiliados.php');
        }

        try {
            // Buscar dados do jogador
            $jogador = dbFetchOne(
                "SELECT id, nome, afiliado_ativo FROM jogador WHERE id = ? AND codigo_afiliado IS NOT NULL", 
                [$jogador_id]
            );

            if (!$jogador) {
                throw new Exception('Jogador/afiliado não encontrado');
            }

            // Alternar status
            $novoStatus = $jogador['afiliado_ativo'] === 'ativo' ? 'inativo' : 'ativo';
            
            $success = dbUpdate(
                'jogador',
                ['afiliado_ativo' => $novoStatus],
                'id = ?',
                [$jogador_id]
            );

            if ($success) {
                $statusTexto = $novoStatus === 'ativo' ? 'ativado' : 'desativado';
                logAdminAction(
                    'toggle_afiliado_status', 
                    "Afiliado {$jogador['nome']} {$statusTexto}", 
                    ['jogador_id' => $jogador_id, 'novo_status' => $novoStatus]
                );
                setSuccess("Afiliado {$statusTexto} com sucesso");
            } else {
                throw new Exception('Erro ao atualizar status');
            }
        } catch (Exception $e) {
            setError('Erro ao alterar status: ' . $e->getMessage());
        }
        break;

    case 'pagar_comissao':
        $comissao_id = (int)$_POST['comissao_id'];
        if (!$comissao_id) {
            setError('ID da comissão inválido');
            redirect(APP_URL . '/admin/afiliados.php');
        }

        try {
            // Buscar dados da comissão
            $comissao = dbFetchOne(
                "SELECT c.*, a.nome as afiliado_nome 
                 FROM afiliados_comissoes c 
                 JOIN afiliados a ON c.afiliado_id = a.id 
                 WHERE c.id = ?", 
                [$comissao_id]
            );

            if (!$comissao) {
                throw new Exception('Comissão não encontrada');
            }

            if ($comissao['status'] !== 'pendente') {
                throw new Exception('Esta comissão já foi paga');
            }

            // Iniciar transação
            dbBeginTransaction();

            // Atualizar status da comissão
            $success = dbUpdate(
                'afiliados_comissoes',
                ['status' => 'pago', 'data_pagamento' => date('Y-m-d H:i:s')],
                'id = ?',
                [$comissao_id]
            );

            // Atualizar saldo do afiliado
            if ($success) {
                $success = dbExecute(
                    "UPDATE afiliados 
                     SET saldo = saldo - ? 
                     WHERE id = ?",
                    [$comissao['valor_comissao'], $comissao['afiliado_id']]
                );
            }

            if ($success) {
                dbCommit();
                logAdminAction(
                    'pagar_comissao', 
                    "Comissão paga para {$comissao['afiliado_nome']}", 
                    $comissao
                );
                setSuccess('Comissão paga com sucesso');
            } else {
                throw new Exception('Erro ao processar pagamento');
            }
        } catch (Exception $e) {
            dbRollback();
            setError('Erro ao pagar comissão: ' . $e->getMessage());
        }
        break;

    default:
        setError('Ação inválida');
}

// Redirecionar de volta
$redirect = $_POST['redirect'] ?? APP_URL . '/admin/afiliados.php';
redirect($redirect);