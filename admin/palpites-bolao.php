<?php
/**
 * Admin Palpites do Bolão - Bolão Vitimba
 */
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar se o administrador está logado
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Obter ID do bolão da URL
$bolaoId = isset($_GET['bolao_id']) ? (int)$_GET['bolao_id'] : 0;

// Buscar dados do bolão
$bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ?", [$bolaoId]);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Decodificar jogos do bolão
$jogos = json_decode($bolao['jogos'], true);

// Função para obter detalhes do jogo pelo ID
function getJogoDetails($jogoId, $jogos) {
    foreach ($jogos as $jogo) {
        if ($jogo['id'] == $jogoId) {
            return $jogo;
        }
    }
    return null;
}

// Função para formatar o resultado do palpite
function formatarResultadoPalpite($codigo) {
    switch ($codigo) {
        case '0':
            return 'Empate';
        case '1':
            return 'Vitória Casa';
        case '2':
            return 'Vitória Visitante';
        default:
            return 'Desconhecido';
    }
}

// Função para calcular o resultado de um jogo
function calcularResultado($jogo) {
    if ($jogo['resultado_casa'] == $jogo['resultado_visitante']) {
        return '0'; // Empate
    }
    if ($jogo['resultado_casa'] > $jogo['resultado_visitante']) {
        return '1'; // Casa vence
    }
    return '2'; // Visitante vence
}

// Buscar todos os registros da tabela palpites para este bolão
$palpites = dbFetchAll("
    SELECT p.*, j.nome as nome_jogador, j.email as email_jogador 
    FROM palpites p 
    LEFT JOIN jogador j ON p.jogador_id = j.id 
    WHERE p.bolao_id = ? 
    ORDER BY p.data_palpite DESC
", [$bolaoId]);

// Ordenar os palpites por número de acertos
usort($palpites, function($a, $b) use ($jogos) {
    // Calcular acertos para o palpite A
    $palpitesJsonA = json_decode($a['palpites'], true);
    $totalAcertosA = 0;
    if ($palpitesJsonA && isset($palpitesJsonA['jogos']) && $jogos) {
        foreach ($jogos as $jogo) {
            if ($jogo['status'] === 'FT' && isset($palpitesJsonA['jogos'][$jogo['id']])) {
                $resultadoReal = calcularResultado($jogo);
                if ($palpitesJsonA['jogos'][$jogo['id']] === $resultadoReal) {
                    $totalAcertosA++;
                }
            }
        }
    }

    // Calcular acertos para o palpite B
    $palpitesJsonB = json_decode($b['palpites'], true);
    $totalAcertosB = 0;
    if ($palpitesJsonB && isset($palpitesJsonB['jogos']) && $jogos) {
        foreach ($jogos as $jogo) {
            if ($jogo['status'] === 'FT' && isset($palpitesJsonB['jogos'][$jogo['id']])) {
                $resultadoReal = calcularResultado($jogo);
                if ($palpitesJsonB['jogos'][$jogo['id']] === $resultadoReal) {
                    $totalAcertosB++;
                }
            }
        }
    }

    // Ordenar por número de acertos (decrescente)
    return $totalAcertosB - $totalAcertosA;
});

// Template
$pageTitle = "Palpites do Bolão - " . htmlspecialchars($bolao['nome']);
$currentPage = "boloes";
require_once '../templates/admin/header.php';
?>

<!-- Adiciona Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">Palpites do Bolão</h1>
        <a href="<?= APP_URL ?>/admin/bolao.php?id=<?= $bolaoId ?>" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Voltar para o Bolão
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-list"></i>
            Lista de Palpites - <?= htmlspecialchars($bolao['nome']) ?>
        </div>
        <div class="card-body">
            <?php if (empty($palpites)): ?>
                <div class="alert alert-info">
                    Nenhum palpite encontrado para este bolão.
                </div>
            <?php else: ?>
                <!-- Filtros -->
                <div class="row mb-3">
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-filter"></i></span>
                            <input type="text" class="form-control" id="filtroId" placeholder="Filtrar ID" onkeyup="filtrarTabela(0, this.value)">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-filter"></i></span>
                            <input type="text" class="form-control" id="filtroJogador" placeholder="Filtrar Jogador" onkeyup="filtrarTabela(1, this.value)">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-filter"></i></span>
                            <input type="text" class="form-control" id="filtroData" placeholder="Filtrar Data" onkeyup="filtrarTabela(2, this.value)">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-filter"></i></span>
                            <select class="form-control" id="filtroStatus" onchange="filtrarTabela(3, this.value)">
                                <option value="">Todos os Status</option>
                                <option value="pago">Pago</option>
                                <option value="pendente">Pendente</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-filter"></i></span>
                            <input type="text" class="form-control" id="filtroAcertos" placeholder="Filtrar Acertos" onkeyup="filtrarTabela(4, this.value)">
                        </div>
                    </div>
                    <?php if (isset($palpites[0]['afiliado_id'])): ?>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-filter"></i></span>
                            <input type="text" class="form-control" id="filtroAfiliado" placeholder="Filtrar Afiliado" onkeyup="filtrarTabela(6, this.value)">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tabela -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="tabelaPalpites">
                        <thead>
                            <tr>
                                <th onclick="ordenarTabela(0)" style="cursor: pointer;">ID <i class="fas fa-sort float-end"></i></th>
                                <th onclick="ordenarTabela(1)" style="cursor: pointer;">Jogador ID <i class="fas fa-sort float-end"></i></th>
                                <th onclick="ordenarTabela(2)" style="cursor: pointer;">Data do Palpite <i class="fas fa-sort float-end"></i></th>
                                <th onclick="ordenarTabela(3)" style="cursor: pointer;">Status <i class="fas fa-sort float-end"></i></th>
                                <th onclick="ordenarTabela(4)" style="cursor: pointer;" class="text-center">Acertos <i class="fas fa-sort float-end"></i></th>
                                <th>Palpites</th>
                                <?php if (isset($palpites[0]['afiliado_id'])): ?>
                                    <th onclick="ordenarTabela(6)" style="cursor: pointer;">Afiliado ID <i class="fas fa-sort float-end"></i></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($palpites as $palpite): 
                                // Calcular acertos
                                $palpitesJson = json_decode($palpite['palpites'], true);
                                $totalAcertos = 0;
                                $jogosFinalizados = 0;
                                
                                if ($palpitesJson && isset($palpitesJson['jogos']) && $jogos) {
                                    foreach ($jogos as $jogo) {
                                        if ($jogo['status'] === 'FT' && isset($palpitesJson['jogos'][$jogo['id']])) {
                                            $jogosFinalizados++;
                                            $resultadoReal = calcularResultado($jogo);
                                            if ($palpitesJson['jogos'][$jogo['id']] === $resultadoReal) {
                                                $totalAcertos++;
                                            }
                                        }
                                    }
                                }
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-secondary">#<?= $palpite['id'] ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($palpite['nome_jogador']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($palpite['data_palpite'])) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($palpite['status'] === 'pago'): ?>
                                            <span class="badge bg-success">Pago</span>
                                        <?php elseif ($palpite['status'] === 'pendente'): ?>
                                            <span class="badge bg-warning">Pendente</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Cancelado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success">
                                            <?= $totalAcertos ?>
                                        </span>
                                        <?php if ($jogosFinalizados > 0): ?>
                                            <small class="text-muted">
                                                de <?= $jogosFinalizados ?> finalizados
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalDetalhes<?= $palpite['id'] ?>">
                                            <i class="fas fa-eye"></i>
                                            Ver palpites
                                        </button>
                                    </td>
                                    <?php if (isset($palpite['afiliado_id'])): ?>
                                        <td>
                                            <span class="badge badge-secondary"><?= $palpite['afiliado_id'] ?: 'N/A' ?></span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../templates/admin/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializa todos os modais do Bootstrap
    var modais = document.querySelectorAll('.modal');
    modais.forEach(function(modal) {
        new bootstrap.Modal(modal, {
            keyboard: true,
            backdrop: true,
            focus: true
        });
        
        // Adiciona evento para garantir que os dados sejam carregados quando a modal for aberta
        modal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var palpiteId = button.getAttribute('data-bs-target').replace('#modalDetalhes', '');
            var modalBody = this.querySelector('.modal-body');
            
            // Garante que o conteúdo da modal seja visível
            if (modalBody) {
                modalBody.style.display = 'block';
            }
        });
    });
});

function filtrarTabela(coluna, valor) {
    var tabela = document.getElementById("tabelaPalpites");
    var tbody = tabela.getElementsByTagName("tbody")[0];
    var tr = tbody.getElementsByTagName("tr");
    
    // Armazena o estado de visibilidade original
    if (!tbody.hasAttribute('data-original-display')) {
        for (var i = 0; i < tr.length; i++) {
            tr[i].setAttribute('data-original-display', tr[i].style.display);
        }
    }
    
    for (var i = 0; i < tr.length; i++) {
        var td = tr[i].getElementsByTagName("td")[coluna];
        if (td) {
            var texto = td.textContent || td.innerText;
            texto = texto.toLowerCase();
            valor = valor.toLowerCase();
            
            // Verifica se a linha corresponde ao filtro
            if (texto.indexOf(valor) > -1) {
                tr[i].style.display = tr[i].getAttribute('data-original-display') || "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}

function ordenarTabela(coluna) {
    const tabela = document.getElementById("tabelaPalpites");
    const tbody = tabela.getElementsByTagName("tbody")[0];
    const linhas = Array.from(tbody.getElementsByTagName("tr"));
    const header = tabela.getElementsByTagName("th")[coluna];
    const icone = header.querySelector('i');
    
    // Alterna entre ascendente e descendente
    const ordemAtual = header.getAttribute('data-ordem') || 'asc';
    const novaOrdem = ordemAtual === 'asc' ? 'desc' : 'asc';
    header.setAttribute('data-ordem', novaOrdem);
    
    // Atualiza o ícone
    icone.className = `fas fa-sort-${novaOrdem === 'asc' ? 'up' : 'down'} float-end`;
    
    // Ordena as linhas
    linhas.sort((a, b) => {
        let valorA = a.getElementsByTagName("td")[coluna].textContent.trim();
        let valorB = b.getElementsByTagName("td")[coluna].textContent.trim();
        
        // Tratamento especial para diferentes tipos de dados
        if (coluna === 0) { // ID
            valorA = parseInt(valorA.replace('#', '')) || 0;
            valorB = parseInt(valorB.replace('#', '')) || 0;
        } else if (coluna === 2) { // Data
            valorA = new Date(valorA.split('/').reverse().join('-'));
            valorB = new Date(valorB.split('/').reverse().join('-'));
        } else if (coluna === 4) { // Acertos
            valorA = parseInt(valorA) || 0;
            valorB = parseInt(valorB) || 0;
        }
        
        if (novaOrdem === 'asc') {
            return valorA > valorB ? 1 : -1;
        } else {
            return valorA < valorB ? 1 : -1;
        }
    });
    
    // Reordena as linhas na tabela
    linhas.forEach(linha => tbody.appendChild(linha));
}

// Adiciona estilo CSS
document.head.insertAdjacentHTML('beforeend', `
    <style>
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
            color: #212529;
        }
        .input-group .form-control {
            border-left: none;
            color: #212529;
        }
        .input-group .form-control:focus {
            border-color: #ced4da;
            box-shadow: none;
            color: #212529;
        }
        .input-group .form-control:focus + .input-group-text {
            border-color: #ced4da;
        }
        .table thead th, .table tbody td, .table tfoot th, .table tfoot td {
            color: #212529 !important;
        }
        .modal-content, .modal-body, .modal-title, .modal-header, .modal-footer {
            color: #212529 !important;
        }
        .form-control, .form-select, .input-group-text {
            color: #212529 !important;
        }
        .badge {
            color: #fff !important;
        }
        .badge.badge-secondary {
            color: #212529 !important;
            background-color: #e2e3e5 !important;
            border: 1px solid #bcbebf;
        }
        .float-end {
            margin-left: 5px;
        }
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
    </style>
`);

function limparFiltros() {
    // Limpa os campos de filtro
    document.getElementById('filtroId').value = '';
    document.getElementById('filtroJogador').value = '';
    document.getElementById('filtroData').value = '';
    document.getElementById('filtroStatus').value = '';
    document.getElementById('filtroAcertos').value = '';
    if (document.getElementById('filtroAfiliado')) {
        document.getElementById('filtroAfiliado').value = '';
    }
    
    // Restaura a visibilidade original das linhas
    var tabela = document.getElementById("tabelaPalpites");
    var tbody = tabela.getElementsByTagName("tbody")[0];
    var tr = tbody.getElementsByTagName("tr");
    
    for (var i = 0; i < tr.length; i++) {
        tr[i].style.display = tr[i].getAttribute('data-original-display') || "";
    }
}
</script>

<!-- Adiciona botão para limpar filtros -->
<div class="row mb-3">
    <div class="col-12">
        <button type="button" class="btn btn-secondary btn-sm" onclick="limparFiltros()">
            <i class="fas fa-times"></i> Limpar Filtros
        </button>
    </div>
</div>

<?php // Renderizar todas as modais fora da tabela ?>
<?php foreach ($palpites as $palpite): ?>
                                        <div class="modal fade" id="modalDetalhes<?= $palpite['id'] ?>" tabindex="-1" aria-labelledby="modalDetalhesLabel<?= $palpite['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="modalDetalhesLabel<?= $palpite['id'] ?>">
                                                            Palpites - ID #<?= $palpite['id'] ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <?php 
                                                        $palpitesJson = json_decode($palpite['palpites'], true);
                                                        if (isset($palpitesJson['jogos'])) {
                                                            echo '<div class="table-responsive">';
                                                            echo '<table class="table table-striped table-bordered mb-0">';
                                                            echo '<thead><tr>';
                                                            echo '<th>Jogo</th>';
                                                            echo '<th>Data</th>';
                                                            echo '<th>Placar</th>';
                                                            echo '<th>Palpite</th>';
                                                            echo '<th>Status</th>';
                                                            echo '</tr></thead><tbody>';
                                                            
                                                            foreach ($palpitesJson['jogos'] as $jogoId => $palpiteResultado) {
                                                                $jogoDetails = getJogoDetails($jogoId, $jogos);
                                                                if ($jogoDetails) {
                                                                    echo '<tr>';
                                                                    // Time Casa vs Time Visitante
                                                                    $timeCasa = isset($jogoDetails['time_casa']) ? htmlspecialchars($jogoDetails['time_casa']) : 'Time não definido';
                                                                    $timeVisitante = isset($jogoDetails['time_visitante']) ? htmlspecialchars($jogoDetails['time_visitante']) : 'Time não definido';
                                                                    echo '<td>' . $timeCasa . ' x ' . $timeVisitante . '</td>';
                                                                    
                                                                    // Data do Jogo (com verificação)
                                                                    if (isset($jogoDetails['data']) && $jogoDetails['data']) {
                                                                        echo '<td>' . date('d/m/Y H:i', strtotime($jogoDetails['data'])) . '</td>';
                                                                    } else {
                                                                        echo '<td>Data não definida</td>';
                                                                    }
                                                                    
                                                                    // Placar (se o jogo já aconteceu)
                                                                    $status = isset($jogoDetails['status']) ? $jogoDetails['status'] : '';
                                                                    if ($status === 'FT' && isset($jogoDetails['resultado_casa']) && isset($jogoDetails['resultado_visitante'])) {
                                                                        echo '<td>' . $jogoDetails['resultado_casa'] . ' x ' . $jogoDetails['resultado_visitante'] . '</td>';
                                                                    } else {
                                                                        echo '<td>Não iniciado</td>';
                                                                    }
                                                                    
                                                                    // Palpite do usuário
                                                                    echo '<td>' . formatarResultadoPalpite($palpiteResultado) . '</td>';
                                                                    
                                                                    // Status do jogo
                                                                    $statusClass = $status === 'FT' ? 'success' : 'warning';
                                                                    $statusText = $status === 'FT' ? 'Finalizado' : 'Pendente';
                                                                    echo '<td><span class="badge bg-' . $statusClass . '">' . $statusText . '</span></td>';
                                                                    echo '</tr>';
                                                                }
                                                            }
                                                            
                                                            echo '</tbody></table>';
                                                            echo '</div>';
                                                        } else {
                                                            echo '<div class="alert alert-warning">Nenhum palpite encontrado.</div>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                            Fechar
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                            <?php endforeach; ?>