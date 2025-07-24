<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verifica se é admin
if (!isAdmin()) {
    setFlashMessage('error', 'Acesso não autorizado');
    redirect(APP_URL . '/admin/login.php');
}

// Configuração da paginação
$itensPorPagina = 20;
$paginaAtual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Filtros
$filtroNome = filter_input(INPUT_GET, 'nome');
$filtroEmail = filter_input(INPUT_GET, 'email');
$filtroStatus = filter_input(INPUT_GET, 'status');
$filtroDataInicio = filter_input(INPUT_GET, 'data_inicio');
$filtroDataFim = filter_input(INPUT_GET, 'data_fim');

try {
    // Construir a query base
    $sqlBase = "
        FROM jogador j
        LEFT JOIN contas c ON c.jogador_id = j.id
        WHERE 1=1";
    $params = [];

    // Adicionar filtros
    if ($filtroNome) {
        $sqlBase .= " AND j.nome LIKE ?";
        $params[] = "%$filtroNome%";
    }
    if ($filtroEmail) {
        $sqlBase .= " AND j.email LIKE ?";
        $params[] = "%$filtroEmail%";
    }
    if ($filtroStatus) {
        $sqlBase .= " AND j.status = ?";
        $params[] = $filtroStatus;
    }
    if ($filtroDataInicio) {
        $sqlBase .= " AND DATE(j.data_cadastro) >= ?";
        $params[] = $filtroDataInicio;
    }
    if ($filtroDataFim) {
        $sqlBase .= " AND DATE(j.data_cadastro) <= ?";
        $params[] = $filtroDataFim;
    }

    // Contar total de registros
    $sqlCount = "SELECT COUNT(DISTINCT j.id) as total " . $sqlBase;
    $totalRegistros = dbFetchOne($sqlCount, $params)['total'];
    $totalPaginas = ceil($totalRegistros / $itensPorPagina);

    // Buscar usuários
    $sql = "
        SELECT 
            j.*,
            COALESCE(c.id, 0) as tem_conta,
            (SELECT COUNT(*) FROM palpites p WHERE p.jogador_id = j.id) as total_palpites,
            (SELECT COUNT(*) FROM participacoes p WHERE p.jogador_id = j.id) as total_participacoes,
            (SELECT COUNT(*) FROM transacoes t INNER JOIN contas c ON t.conta_id = c.id WHERE c.jogador_id = j.id AND t.tipo = 'deposito' AND t.status = 'aprovado') as total_depositos,
            (SELECT COALESCE(SUM(t.valor), 0) FROM transacoes t INNER JOIN contas c ON t.conta_id = c.id WHERE c.jogador_id = j.id AND t.tipo = 'deposito' AND t.status = 'aprovado') as valor_total_depositos
        " . $sqlBase . "
        ORDER BY j.data_cadastro DESC 
        LIMIT ? OFFSET ?";
    
    $params[] = $itensPorPagina;
    $params[] = $offset;
    
    $usuarios = dbFetchAll($sql, $params);

} catch (Exception $e) {
    error_log('Erro ao buscar usuários: ' . $e->getMessage());
    setFlashMessage('error', 'Erro ao buscar usuários');
    $usuarios = [];
}

$pageTitle = "Gestão de Usuários";
include '../templates/admin/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Gestão de Usuários</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
            <i class="fas fa-plus"></i> Novo Usuário
        </button>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($filtroNome) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($filtroEmail) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="ativo" <?= $filtroStatus === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= $filtroStatus === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        <option value="bloqueado" <?= $filtroStatus === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($filtroDataInicio) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($filtroDataFim) ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Usuários -->
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($usuarios)): ?>
                <p class="text-center text-muted py-4">Nenhum usuário encontrado</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Status</th>
                                <th>Cadastro</th>
                                <th>Palpites</th>
                                <th>Participações</th>
                                <th>Depósitos</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?= $usuario['id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($usuario['nome']) ?>
                                        <?php if ($usuario['tem_conta']): ?>
                                            <span class="badge bg-success">Conta Ativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($usuario['email']) ?></td>
                                    <td>
                                        <?php
                                            $statusClass = [
                                                'ativo' => 'success',
                                                'inativo' => 'warning',
                                                'bloqueado' => 'danger'
                                            ][$usuario['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>">
                                            <?= ucfirst($usuario['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= formatDateTime($usuario['data_cadastro']) ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= $usuario['total_palpites'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?= $usuario['total_participacoes'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <strong><?= $usuario['total_depositos'] ?></strong> depósitos
                                        </div>
                                        <small class="text-muted">
                                            Total: R$ <?= number_format($usuario['valor_total_depositos'], 2, ',', '.') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary" 
                                                    onclick="editarUsuario(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger" 
                                                    onclick="excluirUsuario(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php if ($usuario['status'] === 'bloqueado'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-success" 
                                                        onclick="desbloquearUsuario(<?= $usuario['id'] ?>)">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-warning" 
                                                        onclick="bloquearUsuario(<?= $usuario['id'] ?>)">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($paginaAtual > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaAtual - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $inicio = max(1, $paginaAtual - 2);
                        $fim = min($totalPaginas, $paginaAtual + 2);
                        
                        if ($inicio > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => 1])) . '">1</a></li>';
                            if ($inicio > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $inicio; $i <= $fim; $i++) {
                            echo '<li class="page-item ' . ($i == $paginaAtual ? 'active' : '') . '">';
                            echo '<a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => $i])) . '">' . $i . '</a>';
                            echo '</li>';
                        }

                        if ($fim < $totalPaginas) {
                            if ($fim < $totalPaginas - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['pagina' => $totalPaginas])) . '">' . $totalPaginas . '</a></li>';
                        }
                        ?>

                        <?php if ($paginaAtual < $totalPaginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $paginaAtual + 1])) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Novo Usuário -->
<div class="modal fade" id="modalNovoUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovoUsuario">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha</label>
                        <input type="password" class="form-control" name="senha" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="tel" class="form-control" name="telefone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                            <option value="bloqueado">Bloqueado</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarNovoUsuario()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Usuário -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarUsuario">
                    <input type="hidden" name="id">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" name="senha" placeholder="Deixe em branco para manter a senha atual">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="tel" class="form-control" name="telefone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                            <option value="bloqueado">Bloqueado</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarEdicaoUsuario()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Funções para manipulação de usuários
async function editarUsuario(id) {
    try {
        const response = await fetch(`api/usuarios.php?id=${id}`);
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        const form = document.getElementById('formEditarUsuario');
        form.id.value = data.id;
        form.nome.value = data.nome;
        form.email.value = data.email;
        form.telefone.value = data.telefone || '';
        form.status.value = data.status;
        
        const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
        modal.show();
        
    } catch (error) {
        alert('Erro ao carregar dados do usuário: ' + error.message);
    }
}

async function salvarNovoUsuario() {
    try {
        const form = document.getElementById('formNovoUsuario');
        const formData = new FormData(form);
        
        const response = await fetch('api/usuarios.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(Object.fromEntries(formData))
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        alert('Usuário criado com sucesso!');
        location.reload();
        
    } catch (error) {
        alert('Erro ao criar usuário: ' + error.message);
    }
}

async function salvarEdicaoUsuario() {
    try {
        const form = document.getElementById('formEditarUsuario');
        const formData = new FormData(form);
        const id = formData.get('id');
        
        const response = await fetch(`api/usuarios.php?id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(Object.fromEntries(formData))
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        alert('Usuário atualizado com sucesso!');
        location.reload();
        
    } catch (error) {
        alert('Erro ao atualizar usuário: ' + error.message);
    }
}

async function excluirUsuario(id) {
    if (!confirm('Tem certeza que deseja excluir este usuário?')) {
        return;
    }
    
    try {
        const response = await fetch(`api/usuarios.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        alert('Usuário excluído com sucesso!');
        location.reload();
        
    } catch (error) {
        alert('Erro ao excluir usuário: ' + error.message);
    }
}

async function bloquearUsuario(id) {
    if (!confirm('Tem certeza que deseja bloquear este usuário?')) {
        return;
    }
    
    try {
        const response = await fetch(`api/usuarios.php?id=${id}&action=block`, {
            method: 'PUT'
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        alert('Usuário bloqueado com sucesso!');
        location.reload();
        
    } catch (error) {
        alert('Erro ao bloquear usuário: ' + error.message);
    }
}

async function desbloquearUsuario(id) {
    try {
        const response = await fetch(`api/usuarios.php?id=${id}&action=unblock`, {
            method: 'PUT'
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        alert('Usuário desbloqueado com sucesso!');
        location.reload();
        
    } catch (error) {
        alert('Erro ao desbloquear usuário: ' + error.message);
    }
}
</script>

<?php include '../templates/admin/footer.php'; ?> 