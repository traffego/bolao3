<?php
/**
 * Ranking - Bolão Football
 */
require_once 'config/config.php';require_once 'includes/functions.php';

// Get bolão ID from URL
$bolaoId = isset($_GET['bolao_id']) ? (int)$_GET['bolao_id'] : 0;

// Get all bolões for the filter
$boloes = dbFetchAll("SELECT id, nome FROM dados_boloes ORDER BY data_inicio DESC");

// If no specific bolão is selected and there are bolões available, use the first one
if ($bolaoId === 0 && !empty($boloes)) {
    $bolaoId = $boloes[0]['id'];
}

// Get bolão details if ID is provided
$bolao = null;
if ($bolaoId > 0) {
    $bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ?", [$bolaoId]);
}

// Get ranking data
$ranking = [];
if ($bolao) {
    // Get all participants and their points
    $query = "
        SELECT 
            j.id,
            j.nome,
            j.email,
            COUNT(DISTINCT p.jogo_id) as total_palpites,
            SUM(CASE 
                WHEN p.placar_casa = r.placar_casa AND p.placar_visitante = r.placar_visitante THEN c.pontos_acerto_exato
                WHEN (p.placar_casa > p.placar_visitante AND r.placar_casa > r.placar_visitante) OR 
                     (p.placar_casa < p.placar_visitante AND r.placar_casa < r.placar_visitante) OR
                     (p.placar_casa = p.placar_visitante AND r.placar_casa = r.placar_visitante) THEN c.pontos_acerto_vencedor
                WHEN p.placar_casa = r.placar_casa THEN c.pontos_acerto_placar_time1
                WHEN p.placar_visitante = r.placar_visitante THEN c.pontos_acerto_placar_time2
                ELSE c.pontos_erro_total
            END) as pontos_total,
            COUNT(CASE WHEN p.placar_casa = r.placar_casa AND p.placar_visitante = r.placar_visitante THEN 1 END) as acertos_exatos,
            COUNT(CASE 
                WHEN (p.placar_casa > p.placar_visitante AND r.placar_casa > r.placar_visitante) OR 
                     (p.placar_casa < p.placar_visitante AND r.placar_casa < r.placar_visitante) OR
                     (p.placar_casa = p.placar_visitante AND r.placar_casa = r.placar_visitante) THEN 1 
            END) as acertos_vencedor
        FROM jogador j
        JOIN participacoes part ON part.jogador_id = j.id
        LEFT JOIN palpites p ON p.jogador_id = j.id AND p.bolao_id = part.bolao_id
        LEFT JOIN jogos jg ON jg.id = p.jogo_id
        LEFT JOIN resultados r ON r.jogo_id = jg.id
        CROSS JOIN (
            SELECT 
                COALESCE(MAX(CASE nome_configuracao WHEN 'pontos_placar_exato' THEN CAST(valor AS SIGNED) END), 10) as pontos_acerto_exato,
                COALESCE(MAX(CASE nome_configuracao WHEN 'pontos_vencedor' THEN CAST(valor AS SIGNED) END), 5) as pontos_acerto_vencedor,
                COALESCE(MAX(CASE nome_configuracao WHEN 'pontos_empate' THEN CAST(valor AS SIGNED) END), 3) as pontos_acerto_placar_time1,
                COALESCE(MAX(CASE nome_configuracao WHEN 'pontos_empate' THEN CAST(valor AS SIGNED) END), 3) as pontos_acerto_placar_time2,
                0 as pontos_erro_total
            FROM configuracoes 
            WHERE nome_configuracao IN ('pontos_placar_exato', 'pontos_vencedor', 'pontos_empate')
        ) c
        WHERE part.bolao_id = ? AND jg.data_hora < NOW()
        GROUP BY j.id, j.nome, j.email
        ORDER BY pontos_total DESC, acertos_exatos DESC, acertos_vencedor DESC";
    
    $ranking = dbFetchAll($query, [$bolaoId]);
}

// Page title
$pageTitle = $bolao ? 'Ranking: ' . $bolao['nome'] : 'Ranking';

// Include header
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= $pageTitle ?></h1>
    </div>

    <!-- Bolão selector -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-6">
                    <label for="bolao_id" class="form-label">Selecione o Bolão</label>
                    <select class="form-select" id="bolao_id" name="bolao_id" onchange="this.form.submit()">
                        <?php foreach ($boloes as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $bolaoId == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($bolao && !empty($ranking)): ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Posição</th>
                                <th>Jogador</th>
                                <th class="text-center">Pontos</th>
                                <th class="text-center">Palpites</th>
                                <th class="text-center">Acertos Exatos</th>
                                <th class="text-center">Acertos Vencedor</th>
                                <th class="text-center">Aproveitamento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ranking as $index => $player): ?>
                                <tr>
                                    <td><?= $index + 1 ?>º</td>
                                    <td><?= htmlspecialchars($player['nome']) ?></td>
                                    <td class="text-center"><?= number_format($player['pontos_total'], 0, ',', '.') ?></td>
                                    <td class="text-center"><?= $player['total_palpites'] ?></td>
                                    <td class="text-center"><?= $player['acertos_exatos'] ?></td>
                                    <td class="text-center"><?= $player['acertos_vencedor'] ?></td>
                                    <td class="text-center">
                                        <?php
                                        $aproveitamento = $player['total_palpites'] > 0 
                                            ? ($player['acertos_exatos'] + $player['acertos_vencedor']) / $player['total_palpites'] * 100 
                                            : 0;
                                        echo number_format($aproveitamento, 1, ',', '.') . '%';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($bolao): ?>
        <div class="alert alert-info">
            Nenhum resultado disponível para este bolão ainda.
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            Nenhum bolão disponível.
        </div>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?> 