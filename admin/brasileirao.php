<?php
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// IDs dos campeonatos brasileiros
$campeonatosBrasil = [
    71 => 'Brasileirão Série A',
    72 => 'Brasileirão Série B',
    253 => 'Brasileirão Série C',
    254 => 'Brasileirão Série D',
    73 => 'Copa do Brasil'
];

$anoAtual = date('Y');
$jogos = [];
$rodadas = [];

// Obter séries selecionadas
$seriesSelecionadas = isset($_GET['series']) ? $_GET['series'] : [];
$quantidadeJogos = isset($_GET['quantidade_jogos']) ? (int)$_GET['quantidade_jogos'] : 10;

// Buscar jogos das séries selecionadas
if (!empty($seriesSelecionadas)) {
    foreach ($seriesSelecionadas as $campeonatoId) {
        if (isset($campeonatosBrasil[$campeonatoId])) {
            $rodadas = buscarRodasBrasileirao($campeonatoId, $anoAtual);
            
            // Se uma rodada específica foi selecionada
            if (isset($_GET['rodada']) && !empty($_GET['rodada'])) {
                $jogosTemp = buscarJogosRodada($campeonatoId, $anoAtual, $_GET['rodada']);
                if (!empty($jogosTemp)) {
                    $jogos = array_merge($jogos, $jogosTemp);
                }
            }
        }
    }
    
    // Limitar a quantidade de jogos
    if (!empty($jogos)) {
        $jogos = array_slice($jogos, 0, $quantidadeJogos);
    }
}

// Include header
$pageTitle = "Jogos do Brasileirão";
$currentPage = "brasileirao";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4" style="padding-top: 4.5rem;">
    <h1 class="mt-4">Jogos do Brasileirão</h1>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Brasileirão</li>
    </ol>
    
    <?php $flashMessage = getFlashMessage(); ?>
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashMessage['type'] ?> alert-dismissible fade show" role="alert">
            <?= $flashMessage['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <i class="fas fa-futbol me-1"></i>
            Filtrar Jogos do Brasileirão
        </div>
        <div class="card-body">
            <form method="get" action="" id="formBrasileirao">
                <!-- Séries em Checkboxes -->
                <div class="mb-4">
                    <label class="form-label">Selecione as Séries</label>
                    <div class="row">
                        <?php foreach ($campeonatosBrasil as $id => $nome): ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="series[]" value="<?= $id ?>" 
                                           id="serie_<?= $id ?>"
                                           <?= in_array((string)$id, $seriesSelecionadas) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="serie_<?= $id ?>">
                                        <?= $nome ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quantidade de Jogos -->
                <div class="mb-3">
                    <label for="quantidade_jogos" class="form-label">Quantidade de Jogos</label>
                    <input type="number" 
                           class="form-select" 
                           id="quantidade_jogos" 
                           name="quantidade_jogos"
                           min="1"
                           value="<?= $quantidadeJogos ?>"
                           placeholder="Digite a quantidade de jogos">
                </div>

                <?php if (!empty($rodadas)): ?>
                <div class="mb-3">
                    <label for="rodada" class="form-label">Selecione a Rodada</label>
                    <select class="form-select" id="rodada" name="rodada">
                        <option value="">Todas as rodadas</option>
                        <?php foreach ($rodadas as $rodada): ?>
                            <option value="<?= htmlspecialchars($rodada) ?>" 
                                    <?= isset($_GET['rodada']) && $_GET['rodada'] == $rodada ? 'selected' : '' ?>>
                                <?= str_replace('Regular Season - ', 'Rodada ', $rodada) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-search"></i> Buscar Jogos
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($jogos)): ?>
    <div class="card">
        <div class="card-header bg-green text-white">
            <i class="fas fa-list me-1"></i>
            Jogos Encontrados (<?= count($jogos) ?>)
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Times</th>
                            <th>Estádio</th>
                            <th>Campeonato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jogos as $jogo): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($jogo['fixture']['date'])) ?></td>
                                <td>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-end me-2">
                                            <?php if (!empty($jogo['teams']['home']['logo'])): ?>
                                                <img src="<?= $jogo['teams']['home']['logo'] ?>" alt="" style="height: 20px; margin-right: 5px;">
                                            <?php endif; ?>
                                            <?= htmlspecialchars($jogo['teams']['home']['name']) ?>
                                        </div>
                                        <div class="text-center">vs</div>
                                        <div class="ms-2">
                                            <?= htmlspecialchars($jogo['teams']['away']['name']) ?>
                                            <?php if (!empty($jogo['teams']['away']['logo'])): ?>
                                                <img src="<?= $jogo['teams']['away']['logo'] ?>" alt="" style="height: 20px; margin-left: 5px;">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($jogo['fixture']['venue']['name'] ?? '-') ?></td>
                                <td><?= $campeonatosBrasil[$jogo['league']['id']] ?? $jogo['league']['name'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php elseif (!empty($_GET)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Nenhum jogo encontrado com os filtros selecionados.
    </div>
    <?php endif; ?>
</div>

<?php include '../templates/admin/footer.php'; ?> 