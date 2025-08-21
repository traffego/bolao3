<?php
/**
 * Ranking Gamificado - Bolão Vitimba
 * Design mobile-first com elementos visuais atrativos
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

// Get the most recent bolão automatically
$bolao = dbFetchOne("SELECT * FROM dados_boloes ORDER BY data_inicio DESC LIMIT 1");
$bolaoId = $bolao ? $bolao['id'] : 0;

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
                            $playerStats[$jogadorId]['pontos_total'] += 10; // Points for correct guess
                        }
                    }
                }
            }
        }
    }
    
    // Create different rankings
    $rankingPontos = array_values($playerStats);
    $rankingApostas = array_values($playerStats);
    $rankingAcertos = array_values($playerStats);
    
    // Sort by points (descending)
    usort($rankingPontos, function($a, $b) {
        if ($b['pontos_total'] === $a['pontos_total']) {
            return $b['total_acertos'] - $a['total_acertos'];
        }
        return $b['pontos_total'] - $a['pontos_total'];
    });
    
    // Sort by total bets (descending)
    usort($rankingApostas, function($a, $b) {
        if ($b['total_palpites'] === $a['total_palpites']) {
            return $b['total_acertos'] - $a['total_acertos'];
        }
        return $b['total_palpites'] - $a['total_palpites'];
    });
    
    // Sort by hit percentage (descending)
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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



.ranking-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 2rem;
    justify-content: center;
}

.ranking-tab {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.ranking-tab.active {
    background: white;
    color: #667eea;
    border-color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
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
    font-size: 1.2rem;
    font-weight: bold;
    color: #667eea;
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
    color: #333;
}

.ranking-stats {
    font-size: 0.85rem;
    color: #666;
}

.ranking-score {
    text-align: right;
    font-weight: bold;
    color: #667eea;
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
        </div>



        <?php if ($bolao && !empty($rankingPontos)): ?>
            <!-- Ranking tabs -->
            <div class="ranking-tabs">
                <a href="#" class="ranking-tab active" data-tab="pontos">
                    <i class="fas fa-star"></i> Mais Pontos
                </a>
                <a href="#" class="ranking-tab" data-tab="apostas">
                    <i class="fas fa-dice"></i> Mais Apostaram
                </a>
                <a href="#" class="ranking-tab" data-tab="acertos">
                    <i class="fas fa-bullseye"></i> Mais Acertaram
                </a>
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
                        <div class="ranking-item">
                            <div class="ranking-position"><?= $index + 1 ?>º</div>
                            <div class="ranking-avatar" style="background-color: <?= $player['avatar']['color'] ?>">
                                <?= $player['avatar']['initials'] ?>
                            </div>
                            <div class="ranking-info">
                                <div class="ranking-name"><?= htmlspecialchars($player['nome']) ?></div>
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

            <!-- Apostas Tab -->
            <div id="apostas-tab" class="tab-content">
                <?php if (count($rankingApostas) >= 3): ?>
                    <!-- Podium -->
                    <div class="podium-container">
                        <?php for ($i = 0; $i < 3 && $i < count($rankingApostas); $i++): 
                            $player = $rankingApostas[$i];
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
                                    <?= $player['total_palpites'] ?> apostas<br>
                                    <?= $player['total_acertos'] ?> acertos
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

                <!-- Full ranking list -->
                <div class="ranking-list">
                    <?php foreach ($rankingApostas as $index => $player): ?>
                        <div class="ranking-item">
                            <div class="ranking-position"><?= $index + 1 ?>º</div>
                            <div class="ranking-avatar" style="background-color: <?= $player['avatar']['color'] ?>">
                                <?= $player['avatar']['initials'] ?>
                            </div>
                            <div class="ranking-info">
                                <div class="ranking-name"><?= htmlspecialchars($player['nome']) ?></div>
                                <div class="ranking-stats">
                                    <?= $player['total_acertos'] ?> acertos • <?= number_format($player['pontos_total']) ?> pontos
                                    <?php if ($player['jogos_finalizados'] > 0): ?>
                                        • <?= number_format(($player['total_acertos'] / $player['jogos_finalizados']) * 100, 1) ?>% aproveitamento
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ranking-score">
                                <span class="ranking-score-value"><?= $player['total_palpites'] ?></span>
                                <span class="ranking-score-label">apostas</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Acertos Tab -->
            <div id="acertos-tab" class="tab-content">
                <?php if (count($rankingAcertos) >= 3): ?>
                    <!-- Podium -->
                    <div class="podium-container">
                        <?php for ($i = 0; $i < 3 && $i < count($rankingAcertos); $i++): 
                            $player = $rankingAcertos[$i];
                            $classes = ['first', 'second', 'third'];
                            $positions = ['🥇', '🥈', '🥉'];
                            $percentage = $player['jogos_finalizados'] > 0 ? ($player['total_acertos'] / $player['jogos_finalizados']) * 100 : 0;
                        ?>
                            <div class="podium-place <?= $classes[$i] ?>">
                                <div class="podium-position"><?= $positions[$i] ?></div>
                                <div class="podium-avatar" style="background-color: <?= $player['avatar']['color'] ?>">
                                    <?= $player['avatar']['initials'] ?>
                                </div>
                                <div class="podium-name"><?= htmlspecialchars($player['nome']) ?></div>
                                <div class="podium-stats">
                                    <?= number_format($percentage, 1) ?>% acertos<br>
                                    <?= $player['total_acertos'] ?>/<?= $player['jogos_finalizados'] ?> jogos
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

                <!-- Full ranking list -->
                <div class="ranking-list">
                    <?php foreach ($rankingAcertos as $index => $player): 
                        $percentage = $player['jogos_finalizados'] > 0 ? ($player['total_acertos'] / $player['jogos_finalizados']) * 100 : 0;
                    ?>
                        <div class="ranking-item">
                            <div class="ranking-position"><?= $index + 1 ?>º</div>
                            <div class="ranking-avatar" style="background-color: <?= $player['avatar']['color'] ?>">
                                <?= $player['avatar']['initials'] ?>
                            </div>
                            <div class="ranking-info">
                                <div class="ranking-name"><?= htmlspecialchars($player['nome']) ?></div>
                                <div class="ranking-stats">
                                    <?= $player['total_acertos'] ?> acertos em <?= $player['jogos_finalizados'] ?> jogos
                                    • <?= $player['total_palpites'] ?> apostas • <?= number_format($player['pontos_total']) ?> pontos
                                </div>
                            </div>
                            <div class="ranking-score">
                                <span class="ranking-score-value"><?= number_format($percentage, 1) ?>%</span>
                                <span class="ranking-score-label">acertos</span>
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
// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.ranking-tab');
    const contents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs and contents
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Show corresponding content
            const tabName = this.getAttribute('data-tab');
            const content = document.getElementById(tabName + '-tab');
            if (content) {
                content.classList.add('active');
            }
        });
    });
    
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