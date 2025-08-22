<?php
/**
 * Ranking Gamificado - Bolão Vitimba
 * Design mobile-first com elementos visuais atrativos
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

// Get bolão ID from URL parameter or use the most recent one
$bolaoId = filter_input(INPUT_GET, 'bolao_id', FILTER_VALIDATE_INT);

if (!$bolaoId) {
    // Get bolões with palpites pagos, ordered by most recent
    $boloesComPalpites = dbFetchAll("
        SELECT DISTINCT b.* 
        FROM dados_boloes b
        INNER JOIN palpites p ON b.id = p.bolao_id 
        WHERE b.status = 1 AND p.status = 'pago'
        ORDER BY b.data_inicio DESC
    ");
    
    $bolao = null;
    
    // Find the most recent bolão with acertos
    foreach ($boloesComPalpites as $bolaoCandidate) {
        $jogosJson = json_decode($bolaoCandidate['jogos'], true) ?? [];
        $temJogosFinalizados = false;
        
        foreach ($jogosJson as $jogo) {
            if ($jogo['status'] === 'FT') {
                $temJogosFinalizados = true;
                break;
            }
        }
        
        if ($temJogosFinalizados) {
            $bolao = $bolaoCandidate;
            break;
        }
    }
    
    // Fallback to most recent if no bolão with jogos finalizados found
    if (!$bolao) {
        $bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE status = 1 ORDER BY data_inicio DESC LIMIT 1");
    }
    
    $bolaoId = $bolao ? $bolao['id'] : 0;
} else {
    // Get specific bolão
    $bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ? AND status = 1", [$bolaoId]);
}

// Get all available bolões for the selector
$todosBoloes = dbFetchAll("SELECT id, nome, data_inicio, data_fim FROM dados_boloes WHERE status = 1 ORDER BY data_inicio DESC");

// Function to calculate resultado from game data
function calcularResultado($jogo) {
    if (!isset($jogo['resultado_casa']) || !isset($jogo['resultado_visitante'])) {
        return null;
    }
    
    $casa = (int)$jogo['resultado_casa'];
    $visitante = (int)$jogo['resultado_visitante'];
    
    if ($casa > $visitante) {
        return '1'; // Vitória casa
    } elseif ($visitante > $casa) {
        return '2'; // Vitória visitante
    } else {
        return '0'; // Empate
    }
}

// Function to generate avatar based on player name
function generateAvatar($nome) {
    $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9'];
    $initials = '';
    $words = explode(' ', trim($nome));
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    if (strlen($initials) < 2 && !empty($words)) {
        $initials = strtoupper(substr($words[0], 0, 2));
    }
    $colorIndex = array_sum(array_map('ord', str_split($nome))) % count($colors);
    return [
        'initials' => $initials,
        'color' => $colors[$colorIndex]
    ];
}

// Get ranking data
$rankingPontos = [];
$rankingApostas = [];
$rankingAcertos = [];

if ($bolao) {
    // Get jogos from bolão JSON
    $jogosJson = json_decode($bolao['jogos'], true) ?? [];
    
    // Get all palpites for this bolão
    $palpites = dbFetchAll("
        SELECT p.*, j.nome as nome_jogador, j.email as email_jogador 
        FROM palpites p 
        LEFT JOIN jogador j ON p.jogador_id = j.id 
        WHERE p.bolao_id = ? AND p.status = 'pago'
        ORDER BY p.data_palpite DESC
    ", [$bolaoId]);
    
    // Calculate stats for each player
    $playerStats = [];
    
    foreach ($palpites as $palpite) {
        $jogadorId = $palpite['jogador_id'];
        $nomeJogador = $palpite['nome_jogador'];
        
        if (!isset($playerStats[$jogadorId])) {
            $playerStats[$jogadorId] = [
                'id' => $jogadorId,
                'nome' => $nomeJogador,
                'email' => $palpite['email_jogador'],
                'total_palpites' => 0,
                'total_acertos' => 0,
                'jogos_finalizados' => 0,
                'pontos_total' => 0,
                'avatar' => generateAvatar($nomeJogador)
            ];
        }
        
        // Decode palpites JSON
        $palpitesJson = json_decode($palpite['palpites'], true);
        
        if ($palpitesJson && isset($palpitesJson['jogos']) && $jogosJson) {
            foreach ($jogosJson as $jogo) {
                if (isset($palpitesJson['jogos'][$jogo['id']])) {
                    $playerStats[$jogadorId]['total_palpites']++;
                    
                    // Check if game is finished
                    if ($jogo['status'] === 'FT') {
                        $playerStats[$jogadorId]['jogos_finalizados']++;
                        $resultadoReal = calcularResultado($jogo);
                        
                        if ($resultadoReal !== null && $palpitesJson['jogos'][$jogo['id']] === $resultadoReal) {
                            $playerStats[$jogadorId]['total_acertos']++;
                            $playerStats[$jogadorId]['pontos_total'] += 1; // Nova política: 1 acerto = 1 ponto
                        }
                    }
                }
            }
        }
    }
    
    // Calculate total games in bolão and finalized games
    $totalJogosBolao = count($jogosJson);
    $totalJogosFinalizados = 0;
    foreach ($jogosJson as $jogo) {
        if ($jogo['status'] === 'FT') {
            $totalJogosFinalizados++;
        }
    }
    
    // Add perfect score flag to player stats (acertou TODOS os jogos do bolão)
    foreach ($playerStats as $jogadorId => $stats) {
        $playerStats[$jogadorId]['acertou_todos'] = ($totalJogosBolao > 0 && $stats['total_acertos'] === $totalJogosBolao);
    }
    
    // Filter only players with at least one hit
    $playersWithHits = array_filter($playerStats, function($player) {
        return $player['total_acertos'] > 0;
    });
    
    // Create different rankings
    $rankingPontos = array_values($playersWithHits);
    $rankingApostas = array_values($playersWithHits);
    $rankingAcertos = array_values($playersWithHits);
    
    // Sort by points (descending)
    usort($rankingPontos, function($a, $b) {
        if ($b['pontos_total'] === $a['pontos_total']) {
            return $b['total_acertos'] - $a['total_acertos'];
        }
        return $b['pontos_total'] - $a['pontos_total'];
    });
    
    // Sort by total bets (descending) - only players with hits
    usort($rankingApostas, function($a, $b) {
        if ($b['total_palpites'] === $a['total_palpites']) {
            return $b['total_acertos'] - $a['total_acertos'];
        }
        return $b['total_palpites'] - $a['total_palpites'];
    });
    
    // Sort by hit percentage (descending) - only players with hits
    usort($rankingAcertos, function($a, $b) {
        $percentA = $a['jogos_finalizados'] > 0 ? ($a['total_acertos'] / $a['jogos_finalizados']) * 100 : 0;
        $percentB = $b['jogos_finalizados'] > 0 ? ($b['total_acertos'] / $b['jogos_finalizados']) * 100 : 0;
        
        if (abs($percentB - $percentA) < 0.1) {
            return $b['total_acertos'] - $a['total_acertos'];
        }
        return $percentB <=> $percentA;
    });
}

// Page title
$pageTitle = $bolao ? 'Ranking: ' . $bolao['nome'] : 'Ranking';

// Include header
include 'templates/header.php';
?>

<style>
/* Mobile-first CSS */
.ranking-container {
    background: linear-gradient(135deg, #022748 0%, #034a6b 50%, #022748 100%);
    min-height: 100vh;
    padding: 1rem 0;
}

.ranking-header {
    text-align: center;
    color: white;
    margin-bottom: 2rem;
}

.ranking-title {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.ranking-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
}

.bolao-selector {
    margin-top: 1.5rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.bolao-selector .form-select {
    background: #ffffff;
    border: 2px solid #b0d524;
    border-radius: 15px;
    padding: 0.75rem 1rem;
    font-weight: 500;
    color: #091848;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.bolao-selector .form-select:focus {
    background: #ffffff;
    border-color: #b0d524;
    box-shadow: 0 0 0 0.2rem rgba(176,213,36,0.25);
    outline: 0;
}

.bolao-selector .form-select option {
    color: #091848;
    background: #ffffff;
}



.ranking-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 2rem;
    justify-content: center;
}

.ranking-section-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: rgba(255,255,255,0.1);
    border-radius: 15px;
    border: 2px solid rgba(176,213,36,0.3);
}

.ranking-section-title {
    color: #b0d524;
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.ranking-section-title i {
    font-size: 1.5rem;
    color: #b0d524;
}

.ranking-section-description {
    color: rgba(255,255,255,0.8);
    font-size: 1rem;
    margin: 0;
    font-weight: 400;
    text-align: left;
}

.tab-title {
    font-weight: bold;
    font-size: 0.95rem;
    margin-bottom: 0.2rem;
}

.tab-description {
    font-size: 0.75rem;
    opacity: 0.8;
    line-height: 1.2;
}

/* Destaque para primeiro colocado */
.ranking-item.first-place {
    background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
    border: 3px solid #FFD700;
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
    transform: scale(1.02);
    margin-bottom: 1rem;
}

.ranking-item.first-place .ranking-name {
    font-weight: bold;
    color: #8B4513;
    text-shadow: 1px 1px 2px rgba(255,255,255,0.8);
}

.ranking-item.first-place .ranking-score-value {
    color: #8B4513;
    font-weight: bold;
    text-shadow: 1px 1px 2px rgba(255,255,255,0.8);
}

/* Destaque especial para quem acertou 100% */
.ranking-item.perfect-score {
    background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 50%, #FF6B9D 100%);
    border: 4px solid #FF1744;
    box-shadow: 0 12px 35px rgba(255, 23, 68, 0.5);
    transform: scale(1.05);
    animation: perfectGlow 2s ease-in-out infinite alternate;
    position: relative;
    overflow: hidden;
}

.ranking-item.perfect-score::before {
    content: '🏆 PERFEITO! 🏆';
    position: absolute;
    top: -5px;
    right: -5px;
    background: #FF1744;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: bold;
    z-index: 10;
}

.ranking-item.perfect-score .ranking-name {
    font-weight: bold;
    color: white;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
}

.ranking-item.perfect-score .ranking-score-value {
    color: white;
    font-weight: bold;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
}

@keyframes perfectGlow {
    from {
        box-shadow: 0 12px 35px rgba(255, 23, 68, 0.5);
    }
    to {
        box-shadow: 0 12px 35px rgba(255, 23, 68, 0.8), 0 0 20px rgba(255, 23, 68, 0.6);
    }
}

/* Estilo para exibição de prêmios */
.prize-display {
    background: rgba(255, 215, 0, 0.2);
    border: 2px solid #FFD700;
    border-radius: 10px;
    padding: 5px 10px;
    margin-left: 10px;
    font-size: 0.85rem;
    font-weight: bold;
    color: #B8860B;
    display: inline-block;
}

.prize-display.perfect {
    background: rgba(255, 23, 68, 0.2);
    border-color: #FF1744;
    color: #C62828;
}

.podium-container {
    display: flex;
    justify-content: center;
    align-items: end;
    margin-bottom: 2rem;
    gap: 1rem;
    flex-wrap: wrap;
}

.podium-place {
    background: rgba(255,255,255,0.95);
    border-radius: 15px;
    padding: 1.5rem 1rem;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
    min-width: 120px;
}

.podium-place:hover {
    transform: translateY(-5px);
}

.podium-place.first {
    order: 2;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: white;
    transform: scale(1.1);
}

.podium-place.second {
    order: 1;
    background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
    color: white;
}

.podium-place.third {
    order: 3;
    background: linear-gradient(135deg, #CD7F32, #B8860B);
    color: white;
}

.podium-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
    margin: 0 auto 0.5rem;
    border: 3px solid rgba(255,255,255,0.3);
}

.podium-position {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.podium-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.podium-stats {
    font-size: 0.8rem;
    opacity: 0.8;
}

.ranking-list {
    background: rgba(255,255,255,0.95);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.ranking-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    transition: background-color 0.3s ease;
}

.ranking-item:hover {
    background-color: rgba(102,126,234,0.1);
}

.ranking-item:last-child {
    border-bottom: none;
}

.ranking-position {
    font-weight: bold;
    font-size: 1.1rem;
    color: #b0d524;
    min-width: 40px;
    text-align: center;
}

.ranking-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
    margin: 0 1rem;
    font-size: 0.9rem;
}

.ranking-info {
    flex: 1;
}

.ranking-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #091848;
}

.ranking-stats {
    font-size: 0.85rem;
    color: #091848;
    opacity: 0.7;
}

.ranking-score {
    text-align: right;
    font-weight: bold;
    color: #b0d524;
}

.ranking-score-value {
    font-size: 1.1rem;
    display: block;
}

.ranking-score-label {
    font-size: 0.75rem;
    opacity: 0.7;
    font-weight: normal;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: white;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .ranking-title {
        font-size: 1.5rem;
    }
    
    .podium-container {
        flex-direction: column;
        align-items: center;
    }
    
    .podium-place {
        width: 100%;
        max-width: 300px;
    }
    
    .podium-place.first {
        order: 1;
        transform: none;
    }
    
    .podium-place.second {
        order: 2;
    }
    
    .podium-place.third {
        order: 3;
    }
    
    .ranking-tabs {
        flex-direction: column;
    }
    
    .ranking-tab {
        text-align: center;
    }
}

@media (min-width: 769px) {
    .ranking-container {
        padding: 2rem 0;
    }
    
    .container {
        max-width: 1200px;
    }
    
    .podium-container {
        margin-bottom: 3rem;
    }
    
    .ranking-item {
        padding: 1.5rem;
    }
}
</style>

<div class="ranking-container">
    <div class="container">
        <div class="ranking-header">
            <h1 class="ranking-title">
                <i class="fas fa-trophy"></i>
                Ranking Gamificado
            </h1>
            <p class="ranking-subtitle"><?= $bolao ? htmlspecialchars($bolao['nome']) : 'Selecione um bolão' ?></p>
            
            <!-- Seletor de Bolão -->
            <?php if (!empty($todosBoloes)): ?>
                <div class="bolao-selector">
                    <select id="bolaoSelect" class="form-select" onchange="window.location.href='<?= APP_URL ?>/ranking.php?bolao_id=' + this.value">
                        <option value="">Selecione um bolão</option>
                        <?php foreach ($todosBoloes as $bolaoOption): ?>
                            <option value="<?= $bolaoOption['id'] ?>" <?= $bolaoOption['id'] == $bolaoId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bolaoOption['nome']) ?>
                                (<?= date('d/m/Y', strtotime($bolaoOption['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($bolaoOption['data_fim'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>



        <?php if ($bolao && !empty($rankingPontos)): ?>
            <!-- Título da seção -->
            <div class="ranking-section-header">
                <h3 class="ranking-section-title">
                    <i class="fas fa-star"></i> Classificação Geral
                </h3>
                <p class="ranking-section-description">Ranking por pontos totais conquistados</p>
            </div>

            <!-- Pontos Tab -->
            <div id="pontos-tab" class="tab-content active">
                <?php if (count($rankingPontos) >= 3): ?>
                    <!-- Podium -->
                    <div class="podium-container">
                        <?php for ($i = 0; $i < 3 && $i < count($rankingPontos); $i++): 
                            $player = $rankingPontos[$i];
                            $classes = ['first', 'second', 'third'];
                            $positions = ['🥇', '🥈', '🥉'];
                        ?>
                            <div class="podium-place <?= $classes[$i] ?>">
                                <div class="podium-position"><?= $positions[$i] ?></div>
                                <div class="podium-avatar" style="background-color: <?= $player['avatar']['color'] ?>">
                                    <?= $player['avatar']['initials'] ?>
                                </div>
                                <div class="podium-name"><?= htmlspecialchars($player['nome']) ?></div>
                                <div class="podium-stats">
                                    <?= number_format($player['pontos_total']) ?> pontos<br>
                                    <?= $player['total_acertos'] ?> acertos
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

                <!-- Full ranking list -->
                <div class="ranking-list">
                    <?php foreach ($rankingPontos as $index => $player): ?>
                        <?php 
                            $itemClasses = 'ranking-item';
                            if ($index === 0) {
                                $itemClasses .= ' first-place';
                            }
                            if ($player['acertou_todos']) {
                                $itemClasses .= ' perfect-score';
                            }
                        ?>
                        <div class="<?= $itemClasses ?>">
                            <div class="ranking-position"><?= $index + 1 ?>º</div>
                            <div class="ranking-avatar" style="background-color: <?= $player['avatar']['color'] ?>">
                                <?= $player['avatar']['initials'] ?>
                            </div>
                            <div class="ranking-info">
                                <div class="ranking-name">
                                    <?= htmlspecialchars($player['nome']) ?>
                                    <?php if ($index === 0 && $bolao['premio_total'] > 0): ?>
                                        <span class="prize-display">Prêmio: <?= formatMoney($bolao['premio_total']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($player['acertou_todos'] && $bolao['premio_rodada'] > 0): ?>
                                        <span class="prize-display perfect">Prêmio Perfeito: <?= formatMoney($bolao['premio_rodada']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="ranking-stats">
                                    <?= $player['total_acertos'] ?> acertos em <?= $player['jogos_finalizados'] ?> jogos
                                    <?php if ($player['jogos_finalizados'] > 0): ?>
                                        • <?= number_format(($player['total_acertos'] / $player['jogos_finalizados']) * 100, 1) ?>% aproveitamento
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ranking-score">
                                <span class="ranking-score-value"><?= number_format($player['pontos_total']) ?></span>
                                <span class="ranking-score-label">pontos</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>



        <?php elseif ($bolao): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h3>Nenhum resultado disponível</h3>
                <p>Este bolão ainda não possui palpites ou resultados para exibir no ranking.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Nenhum bolão disponível</h3>
                <p>Não há bolões cadastrados no sistema.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Ranking animations
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling and animations
    const rankingItems = document.querySelectorAll('.ranking-item');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    
    rankingItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        item.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(item);
    });
});
</script>

<?php include 'templates/footer.php'; ?>