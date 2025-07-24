<?php
/**
 * Status Topbar Component
 * Componente de status para a área administrativa
 */

require_once __DIR__ . '/../../includes/functions.php';

// Verificar status do banco e da API
$dbStatus = checkDatabaseStatus();
$apiStatus = checkApiFootballStatus();

// Formatar tamanho do banco em MB
function formatSize($bytes) {
    return number_format($bytes / 1024 / 1024, 2) . ' MB';
}

// Formatar data da API
function formatApiDate($date) {
    return date('d/m/Y', strtotime($date));
}
?>

<!-- Status Topbar -->
<div class="status-topbar bg-light border-bottom py-2">
    <div class="container-fluid">
        <div class="row align-items-center">
            <!-- Status do Banco -->
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <i class="fas fa-database <?= $dbStatus['status'] === 'online' ? 'text-success' : 'text-danger' ?> me-2"></i>
                    <div>
                        <span class="status-text"><?= $dbStatus['message'] ?></span>
                        <div class="small text-muted">
                            <?php if ($dbStatus['status'] === 'online'): ?>
                                <span class="me-2">DB: <?= $dbStatus['details']['name'] ?></span>
                                <span class="me-2">|</span>
                                <span class="me-2">Tabelas: <?= $dbStatus['details']['tables']['total'] ?>/<?= $dbStatus['details']['tables']['expected'] ?></span>
                                <span class="me-2">|</span>
                                <span class="me-2">Tamanho: <?= formatSize($dbStatus['details']['size']['total']) ?></span>
                                <span class="me-2">|</span>
                                <span>MySQL <?= $dbStatus['details']['version'] ?></span>
                                
                                <!-- Detalhes em tooltip -->
                                <div class="d-none" id="dbDetailsData">
                                    <div>Host: <?= $dbStatus['details']['host'] ?></div>
                                    <?php foreach ($dbStatus['details']['tables']['status'] as $table): ?>
                                        <div>
                                            <?= $table['label'] ?>: 
                                            <?= $table['exists'] ? $table['records'] . ' registros' : 'Não existe' ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Status da API -->
            <div class="col-md-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-cloud <?= $apiStatus['status'] === 'online' ? 'text-success' : 'text-danger' ?> me-2"></i>
                    <div>
                        <span class="status-text"><?= $apiStatus['message'] ?></span>
                        <?php if ($apiStatus['status'] === 'online'): ?>
                            <div class="small text-muted">
                                <span class="me-2">Requisições: <?= $apiStatus['details']['requests']['current'] ?>/<?= $apiStatus['details']['requests']['limit_day'] ?></span>
                                <span class="me-2">|</span>
                                <span>Plano: 
                                    <?php 
                                    $planLower = strtolower($apiStatus['details']['subscription']['plan']);
                                    if ($planLower === 'free'): ?>
                                        <span class="badge bg-danger blink">FREE</span>
                                    <?php elseif ($planLower === 'pro'): ?>
                                        <span class="badge bg-primary">PRO</span>
                                    <?php else: ?>
                                        <?= $apiStatus['details']['subscription']['plan'] ?>
                                    <?php endif; ?>
                                </span>
                                
                                <!-- Detalhes em tooltip -->
                                <div class="d-none" id="apiDetailsData">
                                    <div>Início: <?= formatApiDate($apiStatus['details']['subscription']['started']) ?></div>
                                    <div>Término: <?= formatApiDate($apiStatus['details']['subscription']['ends']) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Atualização -->
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-sm btn-outline-primary" id="refreshStatus">
                    <i class="fas fa-sync-alt"></i>
                    <span class="d-none d-sm-inline ms-1">Atualizar</span>
                </button>
                <small class="text-muted d-block mt-1">
                    <i class="fas fa-clock me-1"></i>
                    <?= date('H:i:s') ?>
                </small>
            </div>
        </div>
    </div>
</div>

<style>
.status-topbar {
    font-size: 0.85rem;
    border-bottom: 1px solid #dee2e6;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.status-text {
    font-weight: 500;
    font-size: 0.8rem;
}

#refreshStatus {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

#refreshStatus i {
    transition: transform 0.3s ease;
}

#refreshStatus:active i {
    transform: rotate(180deg);
}

/* Tooltips personalizados */
[data-bs-toggle="tooltip"] {
    cursor: help;
}

/* Badge piscante */
.blink {
    animation: blink-animation 1s steps(5, start) infinite;
}

@keyframes blink-animation {
    to {
        opacity: 0.5;
    }
}

/* Responsividade */
@media (max-width: 768px) {
    .status-topbar .col-md-6,
    .status-topbar .col-md-4,
    .status-topbar .col-md-2 {
        margin-bottom: 0.5rem;
    }
    
    .status-topbar .text-end {
        text-align: left !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.getElementById('refreshStatus');
    
    // Inicializar tooltips
    const dbDetails = document.getElementById('dbDetailsData');
    const apiDetails = document.getElementById('apiDetailsData');
    
    if (dbDetails) {
        const dbTooltip = document.createElement('div');
        dbTooltip.innerHTML = dbDetails.innerHTML;
        new bootstrap.Tooltip(dbDetails.parentElement, {
            html: true,
            title: dbTooltip,
            placement: 'bottom'
        });
    }
    
    if (apiDetails) {
        const apiTooltip = document.createElement('div');
        apiTooltip.innerHTML = apiDetails.innerHTML;
        new bootstrap.Tooltip(apiDetails.parentElement, {
            html: true,
            title: apiTooltip,
            placement: 'bottom'
        });
    }
    
    function refreshStatus() {
        const icon = refreshBtn.querySelector('i');
        icon.classList.add('fa-spin');
        refreshBtn.disabled = true;
        
        fetch('<?= APP_URL ?>/admin/status_checker.php?ajax=1')
            .then(response => response.json())
            .then(data => {
                location.reload();
            })
            .catch(error => {
                console.error('Erro ao atualizar status:', error);
                refreshBtn.classList.remove('btn-outline-primary');
                refreshBtn.classList.add('btn-danger');
            })
            .finally(() => {
                icon.classList.remove('fa-spin');
                refreshBtn.disabled = false;
                setTimeout(() => {
                    refreshBtn.classList.remove('btn-danger');
                    refreshBtn.classList.add('btn-outline-primary');
                }, 1000);
            });
    }
    
    refreshBtn.addEventListener('click', function(e) {
        e.preventDefault();
        refreshStatus();
    });
    
    // Atualizar a cada 5 minutos
    setInterval(refreshStatus, 300000);
});
</script> 