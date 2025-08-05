<?php
/**
 * API Keys Management Interface
 * 
 * Admin interface for managing API keys - create, revoke, view usage statistics
 */

require_once '../includes/auth_admin.php';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/classes/Logger.php';
require_once '../includes/classes/ApiKeyManager.php';

$logger = Logger::getInstance();
$apiKeyManager = new ApiKeyManager();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_key':
            $keyName = trim($_POST['key_name'] ?? '');
            $permissions = $_POST['permissions'] ?? [];
            $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            $rateLimit = !empty($_POST['rate_limit']) ? (int)$_POST['rate_limit'] : null;
            
            if (empty($keyName)) {
                $message = 'Key name is required';
                $messageType = 'error';
            } else {
                $result = $apiKeyManager->generateApiKey(
                    $keyName, 
                    $permissions, 
                    $_SESSION['admin_id'] ?? null, 
                    $expiresAt, 
                    $rateLimit
                );
                
                if ($result['success']) {
                    $message = 'API key created successfully! Key: <code>' . htmlspecialchars($result['api_key']) . '</code><br><strong>Save this key now - it will not be shown again!</strong>';
                    $messageType = 'success';
                    
                    $logger->info('API key created via admin interface', [
                        'key_id' => $result['key_id'],
                        'key_name' => $keyName,
                        'created_by' => $_SESSION['admin_id'] ?? 'unknown'
                    ]);
                } else {
                    $message = 'Failed to create API key: ' . htmlspecialchars($result['error']);
                    $messageType = 'error';
                }
            }
            break;
            
        case 'revoke_key':
            $keyId = (int)($_POST['key_id'] ?? 0);
            
            if ($keyId > 0) {
                $success = $apiKeyManager->revokeApiKey($keyId);
                
                if ($success) {
                    $message = 'API key revoked successfully';
                    $messageType = 'success';
                    
                    $logger->info('API key revoked via admin interface', [
                        'key_id' => $keyId,
                        'revoked_by' => $_SESSION['admin_id'] ?? 'unknown'
                    ]);
                } else {
                    $message = 'Failed to revoke API key';
                    $messageType = 'error';
                }
            }
            break;
            
        case 'cleanup_logs':
            $daysToKeep = (int)($_POST['days_to_keep'] ?? 90);
            
            $success = $apiKeyManager->cleanupUsageLogs($daysToKeep);
            
            if ($success) {
                $message = 'Usage logs cleaned up successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to cleanup usage logs';
                $messageType = 'error';
            }
            break;
    }
}

// Get API keys and usage stats
$apiKeys = $apiKeyManager->getApiKeys(false); // Include inactive keys
$usageStats = $apiKeyManager->getUsageStats();

// Create usage stats lookup
$usageStatsLookup = [];
foreach ($usageStats as $stat) {
    $usageStatsLookup[$stat['api_key_id']] = $stat;
}

// Available permissions
$availablePermissions = [
    'webhook_status' => 'Webhook Status - View webhook status and health',
    'webhook_monitor' => 'Webhook Monitor - Access monitoring endpoints',
    'webhook_register' => 'Webhook Register - Register/update webhooks',
    'system_admin' => 'System Admin - Full system administration',
    'api_manage' => 'API Management - Manage other API keys',
    '*' => 'All Permissions - Full access to all endpoints'
];

$pageTitle = 'API Keys Management';
include '../templates/admin/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 d-md-block sidebar">
            <?php include '../templates/admin/sidebar.php'; ?>
        </div>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">API Keys Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createKeyModal">
                        <i class="fas fa-plus"></i> Create New API Key
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- API Keys List -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Existing API Keys</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($apiKeys)): ?>
                        <p class="text-muted">No API keys found. Create your first API key using the button above.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Prefix</th>
                                        <th>Permissions</th>
                                        <th>Status</th>
                                        <th>Usage (30 days)</th>
                                        <th>Last Used</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($apiKeys as $key): ?>
                                        <?php
                                        $permissions = json_decode($key['permissions'], true) ?: [];
                                        $usage = $usageStatsLookup[$key['id']] ?? null;
                                        $isExpired = $key['expires_at'] && strtotime($key['expires_at']) < time();
                                        ?>
                                        <tr class="<?php echo !$key['is_active'] || $isExpired ? 'table-secondary' : ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($key['key_name']); ?></strong>
                                                <br><small class="text-muted">ID: <?php echo $key['id']; ?></small>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($key['key_prefix']); ?></code></td>
                                            <td>
                                                <?php foreach ($permissions as $perm): ?>
                                                    <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($perm); ?></span>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php if (!$key['is_active']): ?>
                                                    <span class="badge bg-danger">Revoked</span>
                                                <?php elseif ($isExpired): ?>
                                                    <span class="badge bg-warning">Expired</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($usage): ?>
                                                    <strong><?php echo number_format($usage['total_requests']); ?></strong> requests<br>
                                                    <small class="text-success"><?php echo number_format($usage['successful_requests']); ?> success</small> |
                                                    <small class="text-danger"><?php echo number_format($usage['failed_requests']); ?> failed</small>
                                                <?php else: ?>
                                                    <span class="text-muted">No usage</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($key['last_used_at']): ?>
                                                    <?php echo date('d/m/Y H:i', strtotime($key['last_used_at'])); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($key['last_used_ip'] ?? ''); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Never used</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($key['expires_at']): ?>
                                                    <?php echo date('d/m/Y H:i', strtotime($key['expires_at'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($key['is_active'] && !$isExpired): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="revokeKey(<?php echo $key['id']; ?>, '<?php echo htmlspecialchars($key['key_name']); ?>')">
                                                        <i class="fas fa-ban"></i> Revoke
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Usage Statistics -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">System Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center">
                                        <h3 class="text-primary"><?php echo count($apiKeys); ?></h3>
                                        <p class="text-muted mb-0">Total Keys</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h3 class="text-success"><?php echo count(array_filter($apiKeys, fn($k) => $k['is_active'])); ?></h3>
                                        <p class="text-muted mb-0">Active Keys</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Log Management</h5>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cleanupModal">
                                <i class="fas fa-trash"></i> Cleanup Logs
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                API key usage logs are stored for monitoring and security purposes. 
                                Regular cleanup helps maintain database performance.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Create API Key Modal -->
<div class="modal fade" id="createKeyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create New API Key</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_key">
                    
                    <div class="mb-3">
                        <label for="key_name" class="form-label">Key Name</label>
                        <input type="text" class="form-control" id="key_name" name="key_name" required
                               placeholder="e.g., Monitoring System, External API, etc.">
                        <div class="form-text">A descriptive name to identify this API key</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <?php foreach ($availablePermissions as $perm => $description): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" 
                                       value="<?php echo htmlspecialchars($perm); ?>" id="perm_<?php echo htmlspecialchars($perm); ?>">
                                <label class="form-check-label" for="perm_<?php echo htmlspecialchars($perm); ?>">
                                    <strong><?php echo htmlspecialchars($perm); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($description); ?></small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label for="expires_at" class="form-label">Expires At (Optional)</label>
                            <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                            <div class="form-text">Leave empty for no expiration</div>
                        </div>
                        <div class="col-md-6">
                            <label for="rate_limit" class="form-label">Rate Limit (Optional)</label>
                            <input type="number" class="form-control" id="rate_limit" name="rate_limit" 
                                   placeholder="Requests per hour">
                            <div class="form-text">Leave empty for no rate limit</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create API Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cleanup Logs Modal -->
<div class="modal fade" id="cleanupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Cleanup Usage Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="cleanup_logs">
                    
                    <div class="mb-3">
                        <label for="days_to_keep" class="form-label">Days to Keep</label>
                        <input type="number" class="form-control" id="days_to_keep" name="days_to_keep" 
                               value="90" min="1" max="365" required>
                        <div class="form-text">Logs older than this many days will be deleted</div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action cannot be undone. Usage logs older than the specified days will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Cleanup Logs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Revoke Key Form (Hidden) -->
<form id="revokeKeyForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="revoke_key">
    <input type="hidden" name="key_id" id="revokeKeyId">
</form>

<script>
function revokeKey(keyId, keyName) {
    if (confirm('Are you sure you want to revoke the API key "' + keyName + '"?\n\nThis action cannot be undone and will immediately disable the key.')) {
        document.getElementById('revokeKeyId').value = keyId;
        document.getElementById('revokeKeyForm').submit();
    }
}

// Auto-select webhook_status permission by default
document.addEventListener('DOMContentLoaded', function() {
    const webhookStatusCheckbox = document.getElementById('perm_webhook_status');
    if (webhookStatusCheckbox) {
        webhookStatusCheckbox.checked = true;
    }
});
</script>

<?php include '../templates/admin/footer.php'; ?>