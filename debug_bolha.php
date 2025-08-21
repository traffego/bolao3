<?php
// Debug Bolha - Exibe códigos de afiliado e referência em tempo real
// Para ser incluído em todas as páginas durante o debug

// Bolha de debug sempre ativa para monitorar códigos de afiliação

// Captura informações da sessão e do usuário logado
$ref_sessao = isset($_SESSION['referral_code']) ? $_SESSION['referral_code'] : 'Não definido';
$ref_get = isset($_GET['ref']) ? $_GET['ref'] : 'Não presente';
$ref_post = isset($_POST['referral_code']) ? $_POST['referral_code'] : 'Não presente';

// Se usuário estiver logado, busca dados do banco
$codigo_afiliado_db = 'Não logado';
$ref_indicacao_db = 'Não logado';
$afiliado_ativo_db = 'Não logado';

if (isset($_SESSION['user_id'])) {
    require_once 'config/database.php';
    
    $stmt = $pdo->prepare("SELECT codigo_afiliado, ref_indicacao, afiliado_ativo FROM jogador WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_data) {
        $codigo_afiliado_db = $user_data['codigo_afiliado'] ?: 'Vazio';
        $ref_indicacao_db = $user_data['ref_indicacao'] ?: 'Vazio';
        $afiliado_ativo_db = $user_data['afiliado_ativo'] ? 'Sim' : 'Não';
    }
}

// Informações da página atual
$pagina_atual = basename($_SERVER['PHP_SELF']);
$url_completa = $_SERVER['REQUEST_URI'];
$timestamp = date('H:i:s');
?>

<style>
#debug-bolha {
    position: fixed;
    top: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.9);
    color: #00ff00;
    padding: 15px;
    border-radius: 10px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    z-index: 9999;
    max-width: 350px;
    border: 2px solid #00ff00;
    box-shadow: 0 0 10px rgba(0, 255, 0, 0.3);
}

#debug-bolha h4 {
    margin: 0 0 10px 0;
    color: #ffff00;
    text-align: center;
    font-size: 12px;
}

#debug-bolha .debug-section {
    margin-bottom: 8px;
    padding: 5px;
    border-left: 3px solid #00ff00;
    padding-left: 8px;
}

#debug-bolha .debug-label {
    color: #ffff00;
    font-weight: bold;
}

#debug-bolha .debug-value {
    color: #00ff00;
    word-break: break-all;
}

#debug-bolha .debug-empty {
    color: #ff6666;
}

#debug-bolha .debug-ok {
    color: #66ff66;
}

#debug-bolha .debug-close {
    position: absolute;
    top: 5px;
    right: 8px;
    color: #ff6666;
    cursor: pointer;
    font-weight: bold;
}
</style>

<div id="debug-bolha">
    <div class="debug-close" onclick="document.getElementById('debug-bolha').style.display='none'">×</div>
    <h4>🔍 DEBUG AFILIAÇÃO</h4>
    
    <div class="debug-section">
        <div class="debug-label">📍 Página:</div>
        <div class="debug-value"><?php echo $pagina_atual; ?></div>
    </div>
    
    <div class="debug-section">
        <div class="debug-label">🕒 Horário:</div>
        <div class="debug-value"><?php echo $timestamp; ?></div>
    </div>
    
    <div class="debug-section">
        <div class="debug-label">🔗 URL Completa:</div>
        <div class="debug-value"><?php echo htmlspecialchars($url_completa); ?></div>
    </div>
    
    <div class="debug-section">
        <div class="debug-label">📥 GET[ref]:</div>
        <div class="debug-value <?php echo $ref_get === 'Não presente' ? 'debug-empty' : 'debug-ok'; ?>">
            <?php echo htmlspecialchars($ref_get); ?>
        </div>
    </div>
    
    <div class="debug-section">
        <div class="debug-label">📤 POST[referral_code]:</div>
        <div class="debug-value <?php echo $ref_post === 'Não presente' ? 'debug-empty' : 'debug-ok'; ?>">
            <?php echo htmlspecialchars($ref_post); ?>
        </div>
    </div>
    
    <div class="debug-section">
        <div class="debug-label">💾 SESSÃO[referral_code]:</div>
        <div class="debug-value <?php echo $ref_sessao === 'Não definido' ? 'debug-empty' : 'debug-ok'; ?>">
            <?php echo htmlspecialchars($ref_sessao); ?>
        </div>
    </div>
    
    <div class="debug-section">
        <div class="debug-label">🏦 DB - Código Afiliado:</div>
        <div class="debug-value <?php echo $codigo_afiliado_db === 'Vazio' || $codigo_afiliado_db === 'Não logado' ? 'debug-empty' : 'debug-ok'; ?>">
            <?php echo htmlspecialchars($codigo_afiliado_db); ?>
        </div>
    </div>
    
    <div class="debug-section">
        <div class="debug-label">🎯 DB - Ref Indicação:</div>
        <div class="debug-value <?php echo $ref_indicacao_db === 'Vazio' || $ref_indicacao_db === 'Não logado' ? 'debug-empty' : 'debug-ok'; ?>">
            <?php echo htmlspecialchars($ref_indicacao_db); ?>
        </div>
    </div>
    
    <div class="debug-section">
        <div class="debug-label">✅ Afiliado Ativo:</div>
        <div class="debug-value <?php echo $afiliado_ativo_db === 'Não' ? 'debug-empty' : 'debug-ok'; ?>">
            <?php echo htmlspecialchars($afiliado_ativo_db); ?>
        </div>
    </div>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="debug-section">
        <div class="debug-label">👤 User ID:</div>
        <div class="debug-value debug-ok"><?php echo $_SESSION['user_id']; ?></div>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto-refresh da bolha a cada 5 segundos para mostrar mudanças em tempo real
setInterval(function() {
    if (document.getElementById('debug-bolha').style.display !== 'none') {
        // Só recarrega se a bolha estiver visível
        var xhr = new XMLHttpRequest();
        xhr.open('GET', window.location.href, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                // Atualiza apenas o timestamp para mostrar que está funcionando
                var timeElement = document.querySelector('#debug-bolha .debug-section:nth-child(3) .debug-value');
                if (timeElement) {
                    timeElement.textContent = new Date().toLocaleTimeString();
                }
            }
        };
        xhr.send();
    }
}, 5000);
</script>