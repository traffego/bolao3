<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar se o administrador está logado
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Adicionar CSS extra para o loader
$extraCss = '
<style>
    .progress-striped {
        height: 6px;
        background-color: #e9ecef;
        border-radius: 3px;
        margin-top: 10px;
        overflow: hidden;
    }
    .progress-striped .progress-bar-animated {
        background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent);
        background-size: 1rem 1rem;
        animation: progress-bar-stripes 1s linear infinite;
    }
    @keyframes progress-bar-stripes {
        from { background-position: 1rem 0; }
        to { background-position: 0 0; }
    }
    .progress-bar {
        height: 100%;
        background-color: #0d6efd;
        transition: width .6s ease;
    }
    #updateModal .modal-content {
        background: rgba(255, 255, 255, 0.95);
    }
    .loader-icon {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>';

// Processar formulário de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_config'])) {
        // Atualizar configurações
        $configs = [
            'atualizacao_automatica' => isset($_POST['atualizacao_automatica']) ? '1' : '0',
            'intervalo_atualizacao' => $_POST['intervalo_atualizacao'],
            'horario_inicio' => $_POST['horario_inicio'],
            'horario_fim' => $_POST['horario_fim'],
            'dias_semana' => implode(',', $_POST['dias_semana'] ?? []),
            'api_provider' => $_POST['api_provider']
        ];

        foreach ($configs as $nome => $valor) {
            dbUpdate(
                'configuracoes',
                ['valor' => $valor],
                'nome_configuracao = ? AND categoria = ?',
                [$nome, 'resultados']
            );
        }

        setFlashMessage('success', 'Configurações atualizadas com sucesso!');
        redirect(APP_URL . '/admin/gerenciar-resultados.php');
    }
}

// Carregar configurações atuais
$configs = [];
$configsQuery = dbFetchAll("SELECT nome_configuracao, valor FROM configuracoes WHERE categoria = 'resultados'");
foreach ($configsQuery as $config) {
    $configs[$config['nome_configuracao']] = $config['valor'];
}

// Template
require_once '../templates/admin/header.php';
?>

<div class="content-wrapper">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
            <h1>Gerenciar Resultados</h1>
            <div>
                <button type="button" id="btnAtualizar" class="btn btn-success btn-lg" onclick="iniciarAtualizacao()">
                    <i class="fas fa-sync-alt"></i> Atualizar Agora
                </button>
            </div>
        </div>
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/index.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Resultados</li>
        </ol>
    </div>

    <!-- Modal de Atualização -->
    <div class="modal fade" id="updateModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <i class="fas fa-sync-alt fa-3x loader-icon text-primary mb-3"></i>
                    <h4 class="mb-3">Atualizando Resultados</h4>
                    <p class="mb-4">Por favor, aguarde enquanto atualizamos os resultados dos jogos...</p>
                    <div class="progress-striped">
                        <div class="progress-bar progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Configurações de Atualização -->
            <div class="card">
                <div class="card-header bg-gradient-primary">
                    <h3 class="card-title">
                        <i class="fas fa-sliders-h mr-2"></i>
                        Configurações de Atualização
                    </h3>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="atualizacao_automatica" name="atualizacao_automatica" <?= ($configs['atualizacao_automatica'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="atualizacao_automatica">Atualização Automática</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Intervalo de Atualização (minutos)</label>
                            <input type="number" class="form-control" name="intervalo_atualizacao" value="<?= htmlspecialchars($configs['intervalo_atualizacao'] ?? '15') ?>" min="5" max="120">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Horário de Início</label>
                                    <input type="time" class="form-control" name="horario_inicio" value="<?= htmlspecialchars($configs['horario_inicio'] ?? '08:00') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Horário de Fim</label>
                                    <input type="time" class="form-control" name="horario_fim" value="<?= htmlspecialchars($configs['horario_fim'] ?? '23:59') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Dias da Semana</label>
                            <div class="row">
                                <?php
                                $diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                                $diasAtivos = explode(',', $configs['dias_semana'] ?? '1,2,3,4,5,6,7');
                                foreach ($diasSemana as $i => $dia) {
                                    $checked = in_array($i + 1, $diasAtivos) ? 'checked' : '';
                                    echo "
                                    <div class='col-sm-3'>
                                        <div class='custom-control custom-checkbox'>
                                            <input type='checkbox' class='custom-control-input' id='dia_{$i}' name='dias_semana[]' value='" . ($i + 1) . "' {$checked}>
                                            <label class='custom-control-label' for='dia_{$i}'>{$dia}</label>
                                        </div>
                                    </div>";
                                }
                                ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Provedor de API</label>
                            <select class="form-control" name="api_provider">
                                <option value="football-api" <?= ($configs['api_provider'] ?? 'football-api') == 'football-api' ? 'selected' : '' ?>>Football API</option>
                                <option value="custom" <?= ($configs['api_provider'] ?? 'football-api') == 'custom' ? 'selected' : '' ?>>API Personalizada</option>
                            </select>
                        </div>

                        <button type="submit" name="save_config" class="btn btn-primary">Salvar Configurações</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php 
// Adicionar JavaScript extra
$extraJs = '
<script>
function iniciarAtualizacao() {
    const modal = new bootstrap.Modal(document.getElementById("updateModal"));
    modal.show();
    
    const progressBar = document.querySelector(".progress-bar");
    let progress = 0;
    
    // Simular progresso
    const interval = setInterval(() => {
        progress += 5;
        if (progress <= 90) {
            progressBar.style.width = progress + "%";
        }
    }, 300);

    // Fazer a requisição AJAX para atualizar
    fetch("' . APP_URL . '/admin/atualizar-jogos.php")
        .then(response => response.text())
        .then(data => {
            clearInterval(interval);
            progressBar.style.width = "100%";
            
            setTimeout(() => {
                modal.hide();
                // Mostrar mensagem de sucesso animada
                Swal.fire({
                    title: "Atualização Concluída!",
                    text: "Todos os resultados foram atualizados com sucesso!",
                    icon: "success",
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true,
                    iconColor: "#28a745",
                    background: "#ffffff",
                    customClass: {
                        popup: "animate__animated animate__fadeInUp"
                    },
                    showClass: {
                        popup: "animate__animated animate__fadeInUp"
                    },
                    hideClass: {
                        popup: "animate__animated animate__fadeOutDown"
                    }
                }).then(() => {
                    // Adicionar efeito de highlight nas linhas atualizadas
                    const rows = document.querySelectorAll("tr[data-jogo-id]");
                    rows.forEach(row => {
                        row.style.animation = "highlight 1s ease-in-out";
                    });
                    
                    // Recarregar a página após a animação
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                });
            }, 500);
        })
        .catch(error => {
            clearInterval(interval);
            console.error("Erro:", error);
            modal.hide();
            Swal.fire({
                title: "Erro na Atualização",
                text: "Ocorreu um erro ao atualizar os resultados. Tente novamente.",
                icon: "error",
                confirmButtonText: "OK",
                confirmButtonColor: "#dc3545",
                customClass: {
                    popup: "animate__animated animate__shakeX"
                }
            });
        });
}
</script>

<style>
    @keyframes highlight {
        0% {
            background-color: transparent;
        }
        50% {
            background-color: rgba(40, 167, 69, 0.2);
        }
        100% {
            background-color: transparent;
        }
    }
    
    .swal2-popup {
        padding: 2em;
        border-radius: 1rem;
    }
    
    .swal2-icon {
        width: 5em;
        height: 5em;
        border-width: 0.25em;
        margin: 1.25em auto 1.875em;
    }
    
    .swal2-title {
        font-size: 1.5em;
        font-weight: 600;
        margin: 1em 0;
    }
    
    .swal2-html-container {
        font-size: 1.1em;
        margin: 0.5em 1.6em 0.3em;
    }
    
    .swal2-timer-progress-bar {
        background: rgba(40, 167, 69, 0.6);
    }
</style>';

require_once '../templates/admin/footer.php'; 
?> 