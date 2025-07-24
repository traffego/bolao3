<?php
/**
 * Página de teste do Topbar de Status
 * Demonstra o funcionamento do sistema de status
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

$pageTitle = "Teste do Status Topbar";
include '../templates/admin/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i> 
                    Teste do Sistema de Status
                </h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-lightbulb"></i> Como funciona:</h5>
                    <ul class="mb-0">
                        <li><strong>Topbar de Status:</strong> Mostra o status do banco de dados e da API Football em tempo real</li>
                        <li><strong>Atualização Automática:</strong> O status é atualizado automaticamente a cada 5 minutos</li>
                        <li><strong>Atualização Manual:</strong> Clique no botão "Atualizar" para verificar o status imediatamente</li>
                        <li><strong>Indicadores Visuais:</strong> Cores diferentes para cada status (verde=online, vermelho=offline, amarelo=erro)</li>
                    </ul>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-database"></i> Status do Banco de Dados</h5>
                            </div>
                            <div class="card-body">
                                <p>O sistema verifica:</p>
                                <ul>
                                    <li>Conexão com o banco de dados</li>
                                    <li>Existência das tabelas principais</li>
                                    <li>Contagem de registros importantes</li>
                                    <li>Integridade da estrutura</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-cloud"></i> Status da API Football</h5>
                            </div>
                            <div class="card-body">
                                <p>O sistema verifica:</p>
                                <ul>
                                    <li>Conexão com a API Football</li>
                                    <li>Configuração da chave da API</li>
                                    <li>Teste de requisição simples</li>
                                    <li>Contagem de requisições do dia</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-code"></i> Informações Técnicas</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Arquivos Criados:</h6>
                                        <ul>
                                            <li><code>admin/status_checker.php</code> - Classe para verificar status</li>
                                            <li><code>templates/admin/status_topbar.php</code> - Componente do topbar</li>
                                            <li><code>templates/admin/header.php</code> - Header atualizado</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Funcionalidades:</h6>
                                        <ul>
                                            <li>Verificação em tempo real</li>
                                            <li>Atualização via AJAX</li>
                                            <li>Indicadores visuais</li>
                                            <li>Responsivo para mobile</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle"></i> Status Atual:</h6>
                            <p class="mb-0">
                                Olhe para o topbar acima para ver o status atual do sistema. 
                                Os indicadores mostram se o banco de dados e a API Football estão funcionando corretamente.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/admin/footer.php'; ?> 