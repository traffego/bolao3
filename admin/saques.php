<?php
/**
 * Admin Saques - Bolão Vitimba
 */
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Buscar saques pendentes
$sql = "
    SELECT 
        t.*,
        j.nome as jogador_nome,
        j.email as jogador_email,
        j.telefone as jogador_telefone
    FROM transacoes t
    INNER JOIN contas c ON t.conta_id = c.id
    INNER JOIN jogador j ON c.jogador_id = j.id
    WHERE t.tipo = 'saque'
    ORDER BY t.data_solicitacao DESC";

$saques = dbFetchAll($sql);

// Include header
$pageTitle = "Gerenciar Saques";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?= $pageTitle ?></h1>
    
    <?php displayFlashMessages(); ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-money-bill-wave me-1"></i>
            Solicitações de Saque
        </div>
        <div class="card-body">
            <?php if (empty($saques)): ?>
                <div class="alert alert-info">
                    Nenhuma solicitação de saque pendente.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Jogador</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saques as $saque): ?>
                                <tr>
                                    <td><?= formatDateTime($saque['data_solicitacao']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($saque['jogador_nome']) ?>
                                        <br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($saque['jogador_email']) ?>
                                            <?php if ($saque['jogador_telefone']): ?>
                                                <br><?= htmlspecialchars($saque['jogador_telefone']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <?= formatMoney($saque['valor']) ?>
                                    </td>
                                    <td>
                                        <?php
                                            $statusClass = match($saque['status']) {
                                                'aprovado' => 'success',
                                                'rejeitado' => 'danger',
                                                'pendente' => 'warning',
                                                default => 'secondary'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>">
                                            <?= ucfirst($saque['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($saque['status'] === 'pendente'): ?>
                                            <button type="button" 
                                                    class="btn btn-success btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalAprovar" 
                                                    data-saque-id="<?= $saque['id'] ?>"
                                                    data-valor="<?= $saque['valor'] ?>"
                                                    data-jogador="<?= htmlspecialchars($saque['jogador_nome']) ?>">
                                                <i class="fas fa-check"></i> Aprovar
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-danger btn-sm"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalRejeitar" 
                                                    data-saque-id="<?= $saque['id'] ?>"
                                                    data-valor="<?= $saque['valor'] ?>"
                                                    data-jogador="<?= htmlspecialchars($saque['jogador_nome']) ?>">
                                                <i class="fas fa-times"></i> Rejeitar
                                            </button>
                                        <?php else: ?>
                                            <?php if ($saque['status'] === 'aprovado' && !empty($saque['comprovante_url'])): ?>
                                                <a href="<?= $saque['comprovante_url'] ?>" 
                                                   target="_blank" 
                                                   class="btn btn-info btn-sm">
                                                    <i class="fas fa-file-alt"></i> Comprovante
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Aprovar -->
<div class="modal fade" id="modalAprovar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="processar-saque.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Aprovar Saque</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="acao" value="aprovar">
                    <input type="hidden" name="saque_id" id="aprovarSaqueId">
                    
                    <div class="alert alert-info">
                        Confirmando aprovação do saque de <strong id="aprovarValor"></strong> 
                        para o jogador <strong id="aprovarJogador"></strong>.
                    </div>

                    <div class="mb-3">
                        <label for="comprovante" class="form-label">Comprovante do Pagamento</label>
                        <input type="file" class="form-control" id="comprovante" name="comprovante" required>
                        <div class="form-text">Anexe o comprovante do PIX realizado.</div>
                    </div>

                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                        <div class="form-text">Opcional: Adicione observações sobre o pagamento.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Confirmar Aprovação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Rejeitar -->
<div class="modal fade" id="modalRejeitar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="processar-saque.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Rejeitar Saque</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="acao" value="rejeitar">
                    <input type="hidden" name="saque_id" id="rejeitarSaqueId">
                    
                    <div class="alert alert-warning">
                        Confirmando rejeição do saque de <strong id="rejeitarValor"></strong> 
                        para o jogador <strong id="rejeitarJogador"></strong>.
                    </div>

                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo da Rejeição</label>
                        <textarea class="form-control" id="motivo" name="motivo" rows="3" required></textarea>
                        <div class="form-text">Informe o motivo da rejeição do saque.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Confirmar Rejeição
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal Aprovar
    const modalAprovar = document.getElementById('modalAprovar');
    if (modalAprovar) {
        modalAprovar.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const saqueId = button.getAttribute('data-saque-id');
            const valor = button.getAttribute('data-valor');
            const jogador = button.getAttribute('data-jogador');
            
            document.getElementById('aprovarSaqueId').value = saqueId;
            document.getElementById('aprovarValor').textContent = formatMoney(valor);
            document.getElementById('aprovarJogador').textContent = jogador;
        });
    }
    
    // Modal Rejeitar
    const modalRejeitar = document.getElementById('modalRejeitar');
    if (modalRejeitar) {
        modalRejeitar.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const saqueId = button.getAttribute('data-saque-id');
            const valor = button.getAttribute('data-valor');
            const jogador = button.getAttribute('data-jogador');
            
            document.getElementById('rejeitarSaqueId').value = saqueId;
            document.getElementById('rejeitarValor').textContent = formatMoney(valor);
            document.getElementById('rejeitarJogador').textContent = jogador;
        });
    }
    
    // Função para formatar valor em moeda
    function formatMoney(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }
});
</script>

<?php include '../templates/admin/footer.php'; ?> 