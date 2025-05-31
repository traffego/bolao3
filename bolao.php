<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/database_functions.php';
require_once __DIR__ . '/includes/functions.php';

// Obter o slug do bolão da URL
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

// Se não tiver slug, redireciona para a lista de bolões públicos
if (empty($slug)) {
    redirect(APP_URL . '/boloes.php');
}

// Buscar dados do bolão
$bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE slug = ? AND status = 1", [$slug]);

// Se bolão não existe ou não está ativo
if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado ou não está ativo.');
    redirect(APP_URL . '/boloes.php');
}

// Se bolão não é público e usuário não está logado, redirecionar
if ($bolao['publico'] != 1 && !isLoggedIn()) {
    setFlashMessage('warning', 'Este bolão é privado. Faça login para acessá-lo.');
    redirect(APP_URL . '/login.php');
}

// Verificar status do usuário logado
$usuarioId = isLoggedIn() ? getCurrentUserId() : 0;
$podeApostar = false;

if ($usuarioId) {
    // Verificar se o usuário já pagou
    $stmt = $pdo->prepare("SELECT pagamento_confirmado FROM jogador WHERE id = ?");
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch();
    
    $podeApostar = $usuario['pagamento_confirmado'];
}

// Decodificar dados JSON
$jogos = json_decode($bolao['jogos'], true) ?: [];
$campeonatos = json_decode($bolao['campeonatos'], true) ?: [];

// Implementar um cache em memória para armazenar os IDs dos times
$timeLogosCache = [];

// Função para obter o logo de um time da API Football
function getTeamLogo($teamName) {
    global $timeLogosCache;
    
    // Se o logo já estiver no cache de memória, retorna
    if (isset($timeLogosCache[$teamName])) {
        return $timeLogosCache[$teamName];
    }
    
    // Diretório para cache de imagens
    $cacheDir = 'cache/team_logos/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    // Nome do arquivo de cache (remover caracteres especiais)
    $cacheFileName = $cacheDir . preg_replace('/[^a-zA-Z0-9]/', '_', $teamName) . '.png';
    
    // Se o arquivo de cache existir e não estiver expirado (7 dias), use-o
    if (file_exists($cacheFileName) && (time() - filemtime($cacheFileName) < 604800)) {
        $logoUrl = APP_URL . '/' . $cacheFileName;
        $timeLogosCache[$teamName] = $logoUrl;
        return $logoUrl;
    }
    
    // Configuração da API
    $apiConfig = getConfig('api_football');
    if (!$apiConfig || empty($apiConfig['api_key'])) {
        // Se não tiver configuração, use uma URL padrão e salve no cache
        $logoUrl = APP_URL . '/assets/img/team-placeholder.png';
        $timeLogosCache[$teamName] = $logoUrl;
        return $logoUrl;
    }
    
    // URL para a API Football de busca por nome do time
    $url = "https://v3.football.api-sports.io/teams?name=" . urlencode($teamName);
    
    // Configuração da requisição
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3, // Timeout reduzido para 3 segundos
        CURLOPT_CONNECTTIMEOUT => 2, // Timeout de conexão para 2 segundos
        CURLOPT_HTTPHEADER => [
            'X-RapidAPI-Key: ' . $apiConfig['api_key'],
            'X-RapidAPI-Host: v3.football.api-sports.io'
        ]
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        // Em caso de erro, usa uma URL padrão
        $logoUrl = APP_URL . '/assets/img/team-placeholder.png';
    } else {
        // Decode da resposta JSON
        $data = json_decode($response, true);
        
        // Verifica se a resposta contém os dados esperados
        if (isset($data['response'][0]['team']['logo'])) {
            $logoUrl = $data['response'][0]['team']['logo'];
            
            // Salvar a imagem no cache local
            file_put_contents($cacheFileName, file_get_contents($logoUrl));
            $logoUrl = APP_URL . '/' . $cacheFileName;
        } else {
            // Caso não encontre, usa uma URL padrão
            $logoUrl = APP_URL . '/assets/img/team-placeholder.png';
        }
    }
    
    // Guarda o logo no cache em memória
    $timeLogosCache[$teamName] = $logoUrl;
    return $logoUrl;
}

// Ordenar jogos por data
usort($jogos, function($a, $b) {
    $dateA = isset($a['data_iso']) ? $a['data_iso'] : $a['data'];
    $dateB = isset($b['data_iso']) ? $b['data_iso'] : $b['data'];
    return strtotime($dateA) - strtotime($dateB);
});

// Verificar se já passou do prazo para palpites
$prazoEncerrado = false;
if (!empty($bolao['data_limite_palpitar'])) {
    $dataLimite = new DateTime($bolao['data_limite_palpitar']);
    $agora = new DateTime();
    $prazoEncerrado = $agora > $dataLimite;
}

// Inicializar variáveis de palpites
$palpites = [];
$palpitesSessao = [];

// Se o usuário estiver logado, verificar palpites salvos no banco
if ($usuarioId) {
    // Verificar se já existe um registro do usuário para este bolão
    $participante = dbFetchOne("SELECT id FROM participacoes WHERE bolao_id = ? AND jogador_id = ?", [$bolao['id'], $usuarioId]);
    
    // Buscar palpites do usuário se ele já participou
    if ($participante) {
        $palpitesUsuario = dbFetchOne("SELECT palpites FROM palpites WHERE bolao_id = ? AND jogador_id = ?", [$bolao['id'], $usuarioId]);
        if ($palpitesUsuario) {
            $palpites = json_decode($palpitesUsuario['palpites'], true) ?: [];
        }
    }
} 
// Verificar se há palpites salvos na sessão
else if (isset($_SESSION['palpites_temp'][$bolao['id']])) {
    $palpitesSessao = $_SESSION['palpites_temp'][$bolao['id']];
}

// Processar envio de palpites
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se o prazo está encerrado
    if ($prazoEncerrado) {
        $mensagem = [
            'tipo' => 'danger',
            'texto' => 'O prazo para envio de palpites já foi encerrado.'
        ];
    } else {
        // Coletar palpites do formulário
        $palpitesForm = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'resultado_') === 0) {
                $jogoId = substr($key, strlen('resultado_'));
                $palpitesForm[$jogoId] = $value; // "1" = casa vence, "0" = empate, "2" = visitante vence
            }
        }

        // Verificar se todos os jogos foram palpitados
        if (count($palpitesForm) !== count($jogos)) {
            $mensagem = [
                'tipo' => 'warning',
                'texto' => 'Você precisa dar palpites para todos os jogos.'
            ];
        } else {
            // Se não estiver logado, redirecionar para login
            if (!isLoggedIn()) {
                $_SESSION['palpites_temp'] = [
                    'bolao_id' => $bolao['id'],
                    'palpites' => $palpitesForm
                ];
                $_SESSION['login_redirect'] = APP_URL . '/bolao.php?slug=' . $slug;
                setFlashMessage('info', 'Por favor, faça login para salvar seus palpites.');
                redirect(APP_URL . '/login.php');
            } 
            // Se estiver logado mas não pagou, redirecionar para pagamento
            elseif (!$podeApostar) {
                $_SESSION['palpites_temp'] = [
                    'bolao_id' => $bolao['id'],
                    'palpites' => $palpitesForm
                ];
                setFlashMessage('warning', 'Você precisa efetuar o pagamento para participar do bolão.');
                redirect(APP_URL . '/pagamento.php');
            }
            // Se estiver tudo ok, salvar no banco
            else {
                try {
                    // Verifica se o usuário já é participante do bolão
                    $participante = dbFetchOne(
                        "SELECT id FROM participacoes WHERE bolao_id = ? AND jogador_id = ?", 
                        [$bolao['id'], $usuarioId]
                    );
                    
                    // Se não for participante, criar registro
                    if (!$participante) {
                        dbInsert('participacoes', [
                            'bolao_id' => $bolao['id'],
                            'jogador_id' => $usuarioId,
                            'data_entrada' => date('Y-m-d H:i:s'),
                            'status' => 1
                        ]);
                    }
                    
                    // Verifica se já tem palpites
                    $palpiteExistente = dbFetchOne(
                        "SELECT id FROM palpites WHERE bolao_id = ? AND jogador_id = ?", 
                        [$bolao['id'], $usuarioId]
                    );
                    
                    // Preparar dados para salvar
                    $palpitesData = [
                        'bolao_id' => $bolao['id'],
                        'jogador_id' => $usuarioId,
                        'palpites' => json_encode($palpitesForm),
                        'data_palpite' => date('Y-m-d H:i:s')
                    ];
                    
                    if ($palpiteExistente) {
                        // Atualiza palpites existentes
                        dbUpdate('palpites', $palpitesData, 'id = ?', [$palpiteExistente['id']]);
                        $mensagem = [
                            'tipo' => 'success',
                            'texto' => 'Seus palpites foram atualizados com sucesso!'
                        ];
                    } else {
                        // Insere novos palpites
                        dbInsert('palpites', $palpitesData);
                        $mensagem = [
                            'tipo' => 'success',
                            'texto' => 'Seus palpites foram registrados com sucesso!'
                        ];
                    }
                    
                    // Limpar palpites temporários da sessão
                    if (isset($_SESSION['palpites_temp'])) {
                        unset($_SESSION['palpites_temp']);
                    }
                    
                } catch (Exception $e) {
                    $mensagem = [
                        'tipo' => 'danger',
                        'texto' => 'Erro ao salvar os palpites: ' . $e->getMessage()
                    ];
                }
            }
        }
    }
}

// Definir quais palpites exibir (prioridade para palpites salvos no banco)
$palpitesExibir = !empty($palpites) ? $palpites : $palpitesSessao;

// Título da página
$pageTitle = $bolao['nome'];
include TEMPLATE_DIR . '/header.php';
?>

<div class="row">
    <!-- Informações do Bolão -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Informações do Bolão</h5>
        </div>
        <div class="card-body">
                <h2 class="mb-3"><?= htmlspecialchars($bolao['nome']) ?></h2>
                <?php if (!empty($bolao['descricao'])): ?>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($bolao['descricao'])) ?></p>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Período:</strong><br>
                    <?= formatDate($bolao['data_inicio']) ?> a <?= formatDate($bolao['data_fim']) ?>
                        </div>
                
                            <?php if (!empty($bolao['data_limite_palpitar'])): ?>
                    <div class="mb-3">
                        <strong>Prazo para Palpites:</strong><br>
                                <?= formatDateTime($bolao['data_limite_palpitar']) ?>
                                <?php if ($prazoEncerrado): ?>
                                    <span class="badge bg-danger">Encerrado</span>
                                <?php endif; ?>
                    </div>
                            <?php endif; ?>
                
                <?php if ($bolao['valor_participacao'] > 0): ?>
                    <div class="mb-3">
                        <strong>Valor de Participação:</strong><br>
                            <?= formatMoney($bolao['valor_participacao']) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($bolao['premio_total'] > 0): ?>
                    <div class="mb-3">
                        <strong>Prêmio Total:</strong><br>
                            <?= formatMoney($bolao['premio_total']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lista de Jogos -->
    <div class="col-md-8">
        <?php if (!empty($jogos)): ?>
            <form method="post" action="<?= APP_URL ?>/confirmar-palpite.php" id="formPalpites">
                <input type="hidden" name="bolao_id" value="<?= $bolao['id'] ?>">
                <input type="hidden" name="bolao_slug" value="<?= $slug ?>">
                
                <?php foreach ($jogos as $jogo): ?>
                    <?php 
                    $jogoId = $jogo['id'];
                    $palpiteJogo = $palpites[$jogoId] ?? $palpitesSessao[$jogoId] ?? null;
                    $disabled = $prazoEncerrado ? 'disabled' : '';
                    ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-4 text-center">
                                    <img src="<?= getTeamLogo($jogo['time_casa']) ?>" alt="<?= $jogo['time_casa'] ?>" class="team-logo mb-2">
                                    <h5><?= htmlspecialchars($jogo['time_casa']) ?></h5>
                                </div>
                                
                                <div class="col-md-4 text-center">
                                    <div class="mb-2">
                                        <small class="text-muted"><?= formatDateTime($jogo['data']) ?></small>
                                                    </div>
                                                    
                                    <?php if (!$prazoEncerrado): ?>
                                        <div class="col-md-4 text-center">
                                            <div class="btn-group" role="group">
                                                <input type="radio" class="btn-check" name="resultado_<?= $jogo['id'] ?>" 
                                                       id="casa_<?= $jogo['id'] ?>" value="1" 
                                                       <?= ($palpiteJogo === "1") ? 'checked' : '' ?> 
                                                       <?= $disabled ?> required>
                                                <label class="btn btn-outline-success" for="casa_<?= $jogo['id'] ?>">
                                                    Casa Vence
                                                </label>

                                                <input type="radio" class="btn-check" name="resultado_<?= $jogo['id'] ?>" 
                                                       id="empate_<?= $jogo['id'] ?>" value="0" 
                                                       <?= ($palpiteJogo === "0") ? 'checked' : '' ?> 
                                                       <?= $disabled ?> required>
                                                <label class="btn btn-outline-warning" for="empate_<?= $jogo['id'] ?>">
                                                    Empate
                                                </label>

                                                <input type="radio" class="btn-check" name="resultado_<?= $jogo['id'] ?>" 
                                                       id="visitante_<?= $jogo['id'] ?>" value="2" 
                                                       <?= ($palpiteJogo === "2") ? 'checked' : '' ?> 
                                                       <?= $disabled ?> required>
                                                <label class="btn btn-outline-danger" for="visitante_<?= $jogo['id'] ?>">
                                                    Visitante Vence
                                                </label>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0">
                                            <?php if ($palpiteJogo): ?>
                                                Seu palpite: 
                                                <?php if ($palpiteJogo['casa'] > $palpiteJogo['visitante']): ?>
                                                    <strong>Vitória do <?= htmlspecialchars($jogo['time_casa']) ?></strong>
                                                <?php elseif ($palpiteJogo['casa'] < $palpiteJogo['visitante']): ?>
                                                    <strong>Vitória do <?= htmlspecialchars($jogo['time_visitante']) ?></strong>
                                                <?php else: ?>
                                                    <strong>Empate</strong>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Prazo encerrado
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                
                                <div class="col-md-4 text-center">
                                    <img src="<?= getTeamLogo($jogo['time_visitante']) ?>" alt="<?= $jogo['time_visitante'] ?>" class="team-logo mb-2">
                                    <h5><?= htmlspecialchars($jogo['time_visitante']) ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$prazoEncerrado): ?>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Salvar Palpites</button>
                    </div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="alert alert-info">
                Nenhum jogo cadastrado neste bolão ainda.
            </div>
    <?php endif; ?>
        </div>
</div>

<?php if (isset($mensagem) && !empty($mensagem)): ?>
    <div class="alert alert-<?= $mensagem['tipo'] ?> mt-3">
        <?= $mensagem['texto'] ?>
    </div>
<?php endif; ?>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 