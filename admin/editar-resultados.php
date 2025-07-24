<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar se o administrador está logado
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Verificar ID do bolão
$bolaoId = $_GET['id'] ?? null;
if (!$bolaoId) {
    setFlashMessage('danger', 'Bolão não especificado.');
    redirect(APP_URL . '/admin/gerenciar-resultados.php');
}

// Carregar dados do bolão
$bolao = dbFetchOne("SELECT id, nome, jogos FROM dados_boloes WHERE id = ?", [$bolaoId]);
if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/gerenciar-resultados.php');
}

// Processar formulário de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_results'])) {
        $jogos = json_decode($bolao['jogos'], true);
        $resultadosJson = ['jogos' => []];
        $temAtualizacao = false;

        foreach ($jogos as &$jogo) {
            $jogoId = $jogo['id'];
            if (isset($_POST["resultado_casa_{$jogoId}"]) && isset($_POST["resultado_visitante_{$jogoId}"])) {
                $resultadoCasa = $_POST["resultado_casa_{$jogoId}"];
                $resultadoVisitante = $_POST["resultado_visitante_{$jogoId}"];

                // Atualizar no JSON de jogos
                if ($resultadoCasa !== '' && $resultadoVisitante !== '') {
                    $jogo['resultado_casa'] = (int)$resultadoCasa;
                    $jogo['resultado_visitante'] = (int)$resultadoVisitante;
                    $jogo['status'] = 'FT'; // Finalizado
                    $temAtualizacao = true;

                    // Determinar o tipo de resultado (0=empate, 1=casa vence, 2=visitante vence)
                    if ($resultadoCasa > $resultadoVisitante) {
                        $resultadosJson['jogos'][$jogoId] = "1";
                    } elseif ($resultadoCasa < $resultadoVisitante) {
                        $resultadosJson['jogos'][$jogoId] = "2";
                    } else {
                        $resultadosJson['jogos'][$jogoId] = "0";
                    }
                }
            }
        }

        // Atualizar no banco de dados
        if ($temAtualizacao) {
            // Atualizar jogos na tabela dados_boloes
            $jogosJson = json_encode($jogos);
            dbUpdate('dados_boloes', ['jogos' => $jogosJson], 'id = ?', [$bolaoId]);

            // Atualizar ou inserir na tabela resultados
            $resultado = dbFetchOne("SELECT id FROM resultados WHERE bolao_id = ?", [$bolaoId]);
            if ($resultado) {
                dbUpdate('resultados', [
                    'resultado' => json_encode($resultadosJson),
                    'data_resultado' => date('Y-m-d H:i:s')
                ], 'bolao_id = ?', [$bolaoId]);
            } else {
                dbInsert('resultados', [
                    'bolao_id' => $bolaoId,
                    'resultado' => json_encode($resultadosJson),
                    'data_resultado' => date('Y-m-d H:i:s')
                ]);
            }

            setFlashMessage('success', 'Resultados atualizados com sucesso!');
            redirect(APP_URL . '/admin/editar-resultados.php?id=' . $bolaoId);
        }
    }
}

// Template
require_once '../templates/admin/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Editar Resultados - <?= htmlspecialchars($bolao['nome']) ?></h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Resultados dos Jogos</h3>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Time Casa</th>
                                        <th>Resultado</th>
                                        <th>Time Visitante</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $jogos = json_decode($bolao['jogos'], true);
                                    if ($jogos): 
                                        foreach ($jogos as $jogo):
                                            $dataJogo = new DateTime($jogo['data_iso']);
                                    ?>
                                    <tr>
                                        <td><?= $dataJogo->format('d/m/Y H:i') ?></td>
                                        <td><?= htmlspecialchars($jogo['time_casa']) ?></td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="resultado_casa_<?= $jogo['id'] ?>" 
                                                       value="<?= isset($jogo['resultado_casa']) ? $jogo['resultado_casa'] : '' ?>" 
                                                       min="0" style="width: 70px">
                                                <div class="input-group-append input-group-prepend">
                                                    <span class="input-group-text">x</span>
                                                </div>
                                                <input type="number" class="form-control" name="resultado_visitante_<?= $jogo['id'] ?>" 
                                                       value="<?= isset($jogo['resultado_visitante']) ? $jogo['resultado_visitante'] : '' ?>" 
                                                       min="0" style="width: 70px">
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($jogo['time_visitante']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $jogo['status'] == 'FT' ? 'success' : 'warning' ?>">
                                                <?= $jogo['status'] == 'FT' ? 'Finalizado' : 'Em Aberto' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php 
                                        endforeach;
                                    endif; 
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            <button type="submit" name="save_results" class="btn btn-primary">Salvar Resultados</button>
                            <a href="<?= APP_URL ?>/admin/gerenciar-resultados.php" class="btn btn-secondary ml-2">Voltar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once '../templates/admin/footer.php'; ?> 