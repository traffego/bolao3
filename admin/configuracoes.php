<?php
/**
 * Admin Configurações - Bolão Vitimba
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Verificar se a extensão OpenSSL está instalada
if (!extension_loaded('openssl')) {
    die("A extensão OpenSSL do PHP não está instalada. Por favor, instale a extensão OpenSSL para continuar.");
}

// Obter conexão com o banco de dados
global $pdo;
if (!$pdo) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        die("Erro na conexão com o banco de dados: " . $e->getMessage());
    }
}

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Função para converter P12 para PEM
function convertP12ToPem($p12Path, $p12Password = '') {
    $certDir = dirname($p12Path);
    $pemKeyPath = $certDir . '/certificate.key.pem';
    $pemCertPath = $certDir . '/certificate.cert.pem';

    // Ler o conteúdo do arquivo P12
    $p12Content = file_get_contents($p12Path);
    if ($p12Content === false) {
        throw new Exception("Erro ao ler o arquivo P12");
    }

    // Converter P12 para PEM
    if (!openssl_pkcs12_read($p12Content, $certs, $p12Password)) {
        throw new Exception("Erro ao ler o certificado P12: " . openssl_error_string());
    }

    // Salvar a chave privada
    if (!file_put_contents($pemKeyPath, $certs['pkey'])) {
        throw new Exception("Erro ao salvar a chave privada PEM");
    }

    // Salvar o certificado
    if (!file_put_contents($pemCertPath, $certs['cert'])) {
        throw new Exception("Erro ao salvar o certificado PEM");
    }

    return [
        'key' => $pemKeyPath,
        'cert' => $pemCertPath
    ];
}

// Função para validar chave Pix
function validarChavePix($chave) {
    // CPF
    if (preg_match('/^\d{11}$/', $chave)) {
        return validarCPF($chave);
    }
    
    // CNPJ
    if (preg_match('/^\d{14}$/', $chave)) {
        return validarCNPJ($chave);
    }
    
    // Email
    if (filter_var($chave, FILTER_VALIDATE_EMAIL)) {
        return true;
    }
    
    // Telefone (+5511999999999)
    if (preg_match('/^\+55\d{10,11}$/', $chave)) {
        return true;
    }
    
    // Chave aleatória (UUID)
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $chave)) {
        return true;
    }
    
    return false;
}

// Função para validar CPF
function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    
    // Calcula o primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $cpf[$i] * (10 - $i);
    }
    $resto = $soma % 11;
    $dv1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Verifica o primeiro dígito verificador
    if ($cpf[9] != $dv1) {
        return false;
    }
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += $cpf[$i] * (11 - $i);
    }
    $resto = $soma % 11;
    $dv2 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Verifica o segundo dígito verificador
    if ($cpf[10] != $dv2) {
        return false;
    }
    
    return true;
}

// Função para validar CNPJ
function validarCNPJ($cnpj) {
    // Remove caracteres não numéricos
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Verifica se tem 14 dígitos
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }
    
    // Calcula o primeiro dígito verificador
    $soma = 0;
    $multiplicador = 5;
    for ($i = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $multiplicador;
        $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
    }
    $resto = $soma % 11;
    $dv1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Verifica o primeiro dígito verificador
    if ($cnpj[12] != $dv1) {
        return false;
    }
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    $multiplicador = 6;
    for ($i = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $multiplicador;
        $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
    }
    $resto = $soma % 11;
    $dv2 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Verifica o segundo dígito verificador
    if ($cnpj[13] != $dv2) {
        return false;
    }
    
    return true;
}

// Definir configurações do sistema
$configCategories = [
    'geral' => 'Configurações Gerais',
    'pagamentos' => 'Configurações de Pagamentos',
    'pontuacao' => 'Sistema de Pontuação',
    'emails' => 'Configurações de E-mails',
    'api_football' => 'API Football'
];

$currentCategory = $_GET['categoria'] ?? 'geral';

if (!array_key_exists($currentCategory, $configCategories)) {
    $currentCategory = 'geral';
}

// Verificar token CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setFlashMessage('danger', 'Token de segurança inválido. Por favor, tente novamente.');
        redirect(APP_URL . '/admin/configuracoes.php?categoria=' . $currentCategory);
    }
}

// Gerar novo token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Processar upload do certificado
if (isset($_FILES['certificado']) && $_FILES['certificado']['error'] === 0) {
    try {
        // Verificar token CSRF
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Token de segurança inválido');
        }

    // Verificar se o diretório de certificados existe e tem permissões corretas
    $uploadDir = __DIR__ . '/../config/certificates/';
    if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Não foi possível criar o diretório de certificados');
            }
    }

    if (!is_writable($uploadDir)) {
            throw new Exception('O diretório de certificados não tem permissão de escrita');
        }

        // Validação do arquivo
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($_FILES['certificado']['tmp_name']);

    $allowedMimeTypes = [
            'application/x-pkcs12',    // .p12
            'application/x-pem-file',  // .pem
            'application/octet-stream' // alguns sistemas podem identificar assim
    ];

    $fileExtension = strtolower(pathinfo($_FILES['certificado']['name'], PATHINFO_EXTENSION));
    $maxFileSize = 1024 * 1024; // 1MB

        if (!in_array($fileExtension, ['p12', 'pem'])) {
            throw new Exception('Formato de arquivo inválido. Use apenas .P12 ou .PEM');
        }

        if ($_FILES['certificado']['size'] > $maxFileSize) {
            throw new Exception('O arquivo é muito grande. Tamanho máximo permitido: 1MB');
        }

        // Processar o upload baseado no tipo de arquivo
        if ($fileExtension === 'p12') {
        $p12Path = $uploadDir . 'certificate.p12';
            
            // Fazer backup do arquivo existente se houver
            if (file_exists($p12Path)) {
                $backupPath = $p12Path . '.bak';
                rename($p12Path, $backupPath);
            }

            if (!move_uploaded_file($_FILES['certificado']['tmp_name'], $p12Path)) {
                // Restaurar backup se existir
                if (isset($backupPath) && file_exists($backupPath)) {
                    rename($backupPath, $p12Path);
                }
                throw new Exception('Erro ao fazer upload do certificado P12');
            }

            // Remover backup se upload foi bem sucedido
            if (isset($backupPath) && file_exists($backupPath)) {
                unlink($backupPath);
            }

            try {
                // Converter P12 para PEM
                    $pemFiles = convertP12ToPem($p12Path, ''); // Se tiver senha, adicione aqui
                if (!file_exists($pemFiles['key']) || !file_exists($pemFiles['cert'])) {
                        throw new Exception('Falha ao gerar arquivos PEM');
                }
                setFlashMessage('success', 'Certificado P12 enviado e convertido para PEM com sucesso!');
            } catch (Exception $e) {
                setFlashMessage('warning', 'Certificado P12 enviado, mas houve erro na conversão para PEM: ' . $e->getMessage());
            }
        } elseif ($fileExtension === 'pem') {
            $pemPath = $uploadDir . 'certificate.cert.pem';
            
            // Fazer backup do arquivo existente se houver
            if (file_exists($pemPath)) {
                $backupPath = $pemPath . '.bak';
                rename($pemPath, $backupPath);
            }

            if (!move_uploaded_file($_FILES['certificado']['tmp_name'], $pemPath)) {
                // Restaurar backup se existir
                if (isset($backupPath) && file_exists($backupPath)) {
                    rename($backupPath, $pemPath);
                }
                throw new Exception('Erro ao fazer upload do certificado PEM');
            }

            // Remover backup se upload foi bem sucedido
            if (isset($backupPath) && file_exists($backupPath)) {
                unlink($backupPath);
            }

            // Validar conteúdo do arquivo PEM
            $pemContent = file_get_contents($pemPath);
            if (strpos($pemContent, '-----BEGIN CERTIFICATE-----') === false) {
                unlink($pemPath);
                throw new Exception('Arquivo PEM inválido');
            }

            setFlashMessage('success', 'Certificado PEM enviado com sucesso!');
        }

        // Registrar log do upload
        $logStmt = $pdo->prepare("
            INSERT INTO logs 
                (tipo, descricao, usuario_id, data_hora, ip_address) 
            VALUES 
                ('configuracao', ?, ?, NOW(), ?)
        ");
        $logStmt->execute([
            'Upload de certificado ' . strtoupper($fileExtension),
            $_SESSION['admin_id'],
            $_SERVER['REMOTE_ADDR']
        ]);

        redirect(APP_URL . '/admin/configuracoes.php?categoria=pagamentos');
    } catch (Exception $e) {
        setFlashMessage('danger', 'Erro no upload do certificado: ' . $e->getMessage());
        redirect(APP_URL . '/admin/configuracoes.php?categoria=pagamentos');
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Processar exclusão de certificado
        if (isset($_POST['action']) && $_POST['action'] === 'delete_certificate') {
            if (!isset($_POST['certificate_path'])) {
                throw new Exception('Caminho do certificado não especificado');
            }

            $certificatePath = $_POST['certificate_path'];
            $certDir = __DIR__ . '/../config/certificates/';
            
            // Validar se o caminho está dentro do diretório de certificados
            $realCertPath = realpath($certificatePath);
            $realCertDir = realpath($certDir);
            
            if ($realCertPath === false || $realCertDir === false || 
                strpos($realCertPath, $realCertDir) !== 0) {
                throw new Exception('Caminho do certificado inválido');
            }

            if (!file_exists($certificatePath)) {
                throw new Exception('Certificado não encontrado');
            }

            if (!unlink($certificatePath)) {
                throw new Exception('Não foi possível excluir o certificado');
            }

            setFlashMessage('success', 'Certificado excluído com sucesso!');
            redirect(APP_URL . '/admin/configuracoes.php?categoria=pagamentos');
        }

        if ($currentCategory === 'pagamentos') {
            // Validar dados do formulário
            $requiredFields = ['ambiente', 'client_id', 'client_secret', 'pix_key'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                throw new Exception('Os seguintes campos são obrigatórios: ' . implode(', ', $missingFields));
            }

            // Validar ambiente
            if (!in_array($_POST['ambiente'], ['producao', 'homologacao'])) {
                throw new Exception('Ambiente inválido');
            }

            // Validar chave Pix
            if (!validarChavePix($_POST['pix_key'])) {
                throw new Exception('Formato de chave Pix inválido');
            }

            // Validar Client ID (alfanumérico)
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $_POST['client_id'])) {
                throw new Exception('Client ID inválido');
            }

            // Validar Client Secret (mínimo 8 caracteres) apenas se fornecido
            if (!empty($_POST['client_secret']) && strlen($_POST['client_secret']) < 8) {
                throw new Exception('Client Secret deve ter no mínimo 8 caracteres');
            }

            // Preparar dados do Pix
            $pixConfig = [
                'ambiente' => $_POST['ambiente'],
                'client_id' => $_POST['client_id'],
                'client_secret' => !empty($_POST['client_secret']) ? $_POST['client_secret'] : ($pixConfig['client_secret'] ?? ''),
                'pix_key' => $_POST['pix_key']
            ];

            // Salvar configurações do Pix
            try {
                // Debug - Registrar os dados antes da inserção
                error_log("Tentando salvar configurações PIX: " . print_r($pixConfig, true));
                error_log("POST data recebida: " . print_r($_POST, true));
                
                // Verificar se a tabela existe
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'configuracoes'");
                if ($tableCheck->rowCount() === 0) {
                    // Criar a tabela se não existir
                    $sql = file_get_contents(__DIR__ . '/../sql/create_configuracoes_table.sql');
                    $pdo->exec($sql);
                    error_log("Tabela configuracoes criada");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO configuracoes 
                        (nome_configuracao, valor, categoria, descricao) 
                    VALUES 
                        (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        valor = ?,
                        data_atualizacao = CURRENT_TIMESTAMP
                ");

                $pixConfigJson = json_encode($pixConfig, JSON_PRETTY_PRINT);
                if ($pixConfigJson === false) {
                    error_log("Erro ao codificar JSON: " . json_last_error_msg());
                    throw new Exception('Erro ao codificar as configurações do Pix: ' . json_last_error_msg());
                }

                error_log("JSON a ser salvo: " . $pixConfigJson);

                $stmt->execute([
                    'efi_pix_config',
                    $pixConfigJson,
                    'pagamentos',
                    'Configurações da API Pix da Efí',
                    $pixConfigJson
                ]);
                
                // Debug - Registrar o resultado da operação
                error_log("Configurações PIX salvas com sucesso. Rows afetadas: " . $stmt->rowCount());

                // Verificar se a configuração foi realmente salva
                $checkStmt = $pdo->prepare("
                    SELECT valor 
                    FROM configuracoes 
                    WHERE nome_configuracao = 'efi_pix_config' 
                    AND categoria = 'pagamentos'
                ");
                $checkStmt->execute();
                $savedConfig = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if (!$savedConfig) {
                    error_log("Configuração não encontrada após salvamento");
                    throw new Exception('Configuração não encontrada após salvamento');
                }

                error_log("Configuração recuperada do banco: " . print_r($savedConfig, true));

                // Registrar log da alteração
                $logStmt = $pdo->prepare("
                    INSERT INTO logs 
                        (tipo, descricao, usuario_id, data_hora, ip_address) 
                    VALUES 
                        (?, ?, ?, NOW(), ?)
                ");
                
                $logStmt->execute([
                    'configuracao',
                    'Alteração nas configurações do Pix',
                    $_SESSION['admin_id'],
                    $_SERVER['REMOTE_ADDR']
                ]);

                setFlashMessage('success', 'Configurações do Pix atualizadas com sucesso!');
                
                // Debug - Registrar redirecionamento
                error_log("Redirecionando após salvar configurações");
                redirect(APP_URL . '/admin/configuracoes.php?categoria=pagamentos');
                
            } catch (PDOException $e) {
                error_log("Erro PDO ao salvar configurações: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                throw new Exception('Erro ao salvar no banco de dados: ' . $e->getMessage());
            } catch (Exception $e) {
                error_log("Erro geral ao salvar configurações: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                throw $e;
            }
        } elseif ($currentCategory === 'api_football') {
            // Processar configurações da API Football
            if (isset($_POST['config']['api_football'])) {
                $apiConfig = [
                    'api_key' => trim($_POST['config']['api_football']['api_key']),
                    'base_url' => 'https://v3.football.api-sports.io'
                ];
                
                if (empty($apiConfig['api_key'])) {
                    throw new Exception('A chave da API é obrigatória');
                }

                saveConfig('api_football', $apiConfig, 'Configurações da API Football');
                setFlashMessage('success', 'Configurações da API Football atualizadas com sucesso!');
                redirect(APP_URL . '/admin/configuracoes.php?categoria=api_football');
            }
        } else {
            // Processar configurações gerais
            if (isset($_POST['config'])) {
                foreach ($_POST['config'] as $nome => $valor) {
                    $stmt = $pdo->prepare("
                        UPDATE configuracoes 
                        SET valor = ? 
                        WHERE nome_configuracao = ? 
                        AND categoria = ?
                    ");
                    
                    if (is_array($valor)) {
                        $valor = json_encode($valor);
                    }
                    
                    $stmt->execute([$valor, $nome, $currentCategory]);
                }
                
                setFlashMessage('success', 'Configurações atualizadas com sucesso!');
                redirect(APP_URL . '/admin/configuracoes.php?categoria=' . $currentCategory);
            }
        }
    } catch (Exception $e) {
        setFlashMessage('danger', 'Erro ao salvar configurações: ' . $e->getMessage());
        redirect(APP_URL . '/admin/configuracoes.php?categoria=' . $currentCategory);
    }
}

// Buscar configurações do Pix
$stmt = $pdo->prepare("
    SELECT valor 
    FROM configuracoes 
    WHERE nome_configuracao = 'efi_pix_config' 
    AND categoria = 'pagamentos'
");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);
$pixConfig = [];

if ($config && !empty($config['valor'])) {
    $pixConfig = json_decode($config['valor'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        setFlashMessage('warning', 'Erro ao decodificar configurações do PIX');
        $pixConfig = [];
    }
}

// Debug das configurações
if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    echo '<pre>';
    echo "Configurações do PIX carregadas do banco:\n";
    print_r($pixConfig);
    echo '</pre>';
}

// Garantir que todos os campos existam com valores padrão se necessário
$pixConfig = array_merge([
    'ambiente' => 'producao',
    'client_id' => '',
    'client_secret' => '',
    'pix_key' => ''
], $pixConfig);

// Get configurations for the current category
$configQuery = "SELECT * FROM configuracoes WHERE categoria = ? ORDER BY id ASC";
$configurations = dbFetchAll($configQuery, [$currentCategory]);

// Add default API configuration if it doesn't exist and category is api_football
if ($currentCategory === 'api_football' && empty($configurations)) {
    $apiConfig = getConfig('api_football');
    if (!$apiConfig) {
        saveConfig('api_football', [
            'api_key' => '',
            'base_url' => 'https://v3.football.api-sports.io'
        ], 'Configurações da API Football');
        
        // Fetch configurations again
        $configurations = dbFetchAll($configQuery, [$currentCategory]);
    }
}

// Include header
$pageTitle = "Configurações do Sistema";
$currentPage = "configuracoes";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Configurações do Sistema</h1>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Configurações</li>
    </ol>
    
    <?php displayFlashMessages(); ?>
    
    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-list me-1"></i>
                    Categorias
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($configCategories as $key => $name): ?>
                            <a href="?categoria=<?= $key ?>" class="list-group-item list-group-item-action <?= $currentCategory === $key ? 'active' : '' ?>">
                                <?= $name ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <?php if ($currentCategory === 'pagamentos'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Configurações da API Pix - Efí</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Ambiente</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ambiente" value="producao" id="ambiente_prod" 
                                            <?= $pixConfig['ambiente'] === 'producao' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="ambiente_prod">
                                            Produção
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ambiente" value="homologacao" id="ambiente_hml"
                                            <?= $pixConfig['ambiente'] === 'homologacao' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="ambiente_hml">
                                            Homologação
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="client_id" class="form-label">Client ID</label>
                                    <input type="text" class="form-control" id="client_id" name="client_id" 
                                           value="<?= htmlspecialchars($pixConfig['client_id']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="client_secret" class="form-label">Client Secret</label>
                                    <input type="text" class="form-control" id="client_secret" name="client_secret" 
                                           value="<?= htmlspecialchars($pixConfig['client_secret']) ?>">
                                    <div class="form-text">Client Secret para autenticação na API da Efí</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="pix_key" class="form-label">Chave Pix</label>
                                    <input type="text" class="form-control" id="pix_key" name="pix_key" 
                                           value="<?= htmlspecialchars($pixConfig['pix_key']) ?>" required>
                                    <div class="form-text">Chave Pix que receberá os pagamentos</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">URL do Webhook</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="<?= htmlspecialchars(APP_URL . '/api/webhook_pix.php') ?>" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="copyWebhookUrl(this)" title="Copiar URL do webhook">
                                            <i class="fas fa-copy"></i> Copiar URL
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Esta é a URL que receberá as notificações de pagamento. Configure-a no painel da Efí.
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="certificado" class="form-label">Certificado (.P12 ou .PEM)</label>
                                    <input type="file" class="form-control" id="certificado" name="certificado" accept=".p12,.pem">
                                    <div class="form-text mb-2">
                                        Faça upload do certificado fornecido pela Efí (formato .P12 ou .PEM)
                                    </div>
                                    <?php
                                    $certDir = __DIR__ . '/../config/certificates/';
                                    $certificates = [
                                        'certificate.p12' => $certDir . 'certificate.p12',
                                        'certificate.key.pem' => $certDir . 'certificate.key.pem',
                                        'certificate.cert.pem' => $certDir . 'certificate.cert.pem'
                                    ];
                                    
                                    foreach ($certificates as $fileName => $path):
                                        if (file_exists($path)):
                                    ?>
                                        <div class="d-flex align-items-center p-2 border rounded mb-2">
                                            <i class="fas fa-file-certificate text-primary me-2"></i>
                                            <span class="me-auto"><?= htmlspecialchars($fileName) ?></span>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este certificado?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="delete_certificate">
                                                <input type="hidden" name="certificate_path" value="<?= htmlspecialchars($path) ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Excluir certificado">
                                                    <i class="fas fa-trash-alt"></i> Excluir
                                                </button>
                                            </form>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Instruções:</h6>
                                <ol class="mb-0">
                                    <li>Obtenha suas credenciais no painel da Efí</li>
                                    <li>Faça o upload do certificado .P12 ou .PEM</li>
                                    <li>Configure a URL do webhook no painel da Efí</li>
                                    <li>Teste a integração em ambiente de homologação antes de usar em produção</li>
                                </ol>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i> Salvar Configurações
                            </button>
                            
                            <?php if ($currentCategory === 'pagamentos'): ?>
                                <a href="<?= APP_URL ?>/admin/teste-pix.php" class="btn btn-outline-primary btn-lg ms-2">
                                    <i class="fas fa-vial me-2"></i> Testar Integração
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php elseif ($currentCategory === 'pagamentos'): ?>
                <?php if (!extension_loaded('openssl')): ?>
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle"></i> Atenção!</h5>
                        <p>A extensão OpenSSL não está instalada. Ela é necessária para o processamento seguro dos certificados.</p>
                    </div>
                <?php endif; ?>

                <?php if (!is_writable(__DIR__ . '/../config/certificates/')): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> Atenção!</h5>
                        <p>O diretório de certificados não tem permissão de escrita. Por favor, configure as permissões adequadamente.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-cogs me-1"></i>
                        <?= $configCategories[$currentCategory] ?? 'Configurações' ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if ($currentCategory === 'api_football'): ?>
                                <!-- Configurações especiais para API Football -->
                                <div class="mb-3">
                                    <label class="form-label">Chave da API Football</label>
                                    <input type="text" class="form-control" 
                                           name="config[api_football][api_key]" 
                                           value="<?= htmlspecialchars(getConfig('api_football')['api_key'] ?? '') ?>">
                                    <div class="form-text">
                                        <p>Entre com sua chave da API Football para buscar jogos de campeonatos ao redor do mundo.</p>
                                        <p>Você pode obter uma chave gratuita em <a href="https://www.api-football.com/" target="_blank">https://www.api-football.com/</a></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">URL Base</label>
                                    <input type="text" class="form-control" 
                                           value="<?= htmlspecialchars(getConfig('api_football')['base_url'] ?? 'https://v3.football.api-sports.io') ?>" 
                                           readonly>
                                    <div class="form-text">URL base da API Football (não pode ser alterada)</div>
                                </div>
                                
                                <?php if (!empty(getConfig('api_football')['api_key'])): ?>
                                    <div class="alert alert-info">
                                        <h5><i class="fas fa-info-circle"></i> Informações da API</h5>
                                        <p>A API Football permite buscar dados de jogos, times e campeonatos de todo o mundo.</p>
                                        <p>Com a API configurada, você poderá facilmente:</p>
                                        <ul>
                                            <li>Buscar jogos por país, campeonato e rodada</li>
                                            <li>Criar bolões com jogos de qualquer campeonato</li>
                                            <li>Automatizar a atualização de resultados</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mb-3">
                                        <a href="<?= APP_URL ?>/admin/teste-api.php" class="btn btn-primary">
                                            <i class="fas fa-vial"></i> Testar Conexão com API
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Configurações gerais, de pontuação, etc. -->
                                <?php foreach ($configurations as $config): ?>
                                    <?php 
                                    $valor = $config['valor'] ?? '';
                                    if (is_array($config) && isset($config['valor']) && is_string($config['valor']) && isValidJson($config['valor'])) {
                                        $jsonData = json_decode($config['valor'], true);
                                    } else {
                                        $jsonData = null;
                                    }
                                    ?>
                                    
                                    <div class="mb-4 pb-3 border-bottom">
                                        <h5><?= htmlspecialchars($config['nome_configuracao']) ?></h5>
                                        <p class="text-muted"><?= htmlspecialchars($config['descricao']) ?></p>
                                        
                                        <?php if (is_array($jsonData) && !empty($jsonData)): ?>
                                            <!-- JSON object editing -->
                                            <?php foreach ($jsonData as $key => $value): ?>
                                                <div class="mb-3">
                                                    <label class="form-label"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) ?></label>
                                                    <input type="text" class="form-control" 
                                                           name="config[<?= htmlspecialchars($config['nome_configuracao']) ?>][<?= htmlspecialchars($key) ?>]" 
                                                           value="<?= htmlspecialchars($value) ?>">
                                                </div>
                                            <?php endforeach; ?>
                                        <?php elseif ($jsonData !== null): ?>
                                            <!-- Simple value editing (string, number, boolean) -->
                                            <div class="mb-3">
                                                <input type="text" class="form-control" 
                                                       name="config[<?= htmlspecialchars($config['nome_configuracao']) ?>]" 
                                                       value="<?= htmlspecialchars($jsonData) ?>">
                                            </div>
                                        <?php else: ?>
                                            <!-- Fallback for non-JSON data or empty JSON -->
                                            <div class="mb-3">
                                                <label class="form-label">Valor</label>
                                                <input type="text" class="form-control" 
                                                       name="config[<?= htmlspecialchars($config['nome_configuracao']) ?>]" 
                                                       value="<?= htmlspecialchars($valor) ?>">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (empty($configurations)): ?>
                                    <div class="alert alert-info">
                                        <p>Nenhuma configuração encontrada para esta categoria.</p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($configurations): ?>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Salvar Configurações
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyWebhookUrl(button) {
    const input = button.parentElement.querySelector('input');
    input.select();
    document.execCommand('copy');
    
    // Feedback visual
    const icon = button.querySelector('i');
    const text = button.textContent;
    icon.classList.remove('fa-copy');
    icon.classList.add('fa-check');
    button.innerHTML = '<i class="fas fa-check"></i> Copiado!';
    
    setTimeout(() => {
        icon.classList.remove('fa-check');
        icon.classList.add('fa-copy');
        button.innerHTML = '<i class="fas fa-copy"></i> Copiar URL';
    }, 2000);
}
</script>

<?php include '../templates/admin/footer.php'; ?> 