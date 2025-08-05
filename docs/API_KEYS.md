# Enhanced API Key Management System

This document describes the enhanced API key validation system that replaces hardcoded API keys with database-stored keys for improved security and management.

## Overview

The new API key system provides:

- **Database-stored keys**: API keys are securely stored in the database instead of being hardcoded
- **Granular permissions**: Each key can have specific permissions for different operations
- **Usage tracking**: Monitor API key usage with detailed logs and statistics
- **Rate limiting**: Optional rate limits per API key
- **Expiration dates**: Optional expiration for temporary access
- **Admin interface**: Web-based management for creating, viewing, and revoking keys
- **Backward compatibility**: Migration script ensures existing integrations continue working

## Database Schema

### `api_keys` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | int(11) | Primary key |
| `key_name` | varchar(100) | Human-readable name for the key |
| `api_key` | varchar(64) | The actual API key (SHA-256 hashed) |
| `key_prefix` | varchar(16) | Visible prefix for identification |
| `permissions` | JSON | Array of allowed permissions/scopes |
| `is_active` | tinyint(1) | Whether the key is active |
| `created_by` | int(11) | User ID who created the key |
| `last_used_at` | timestamp | Last time the key was used |
| `last_used_ip` | varchar(45) | IP address of last usage |
| `expires_at` | timestamp | Optional expiration date |
| `usage_count` | int(11) | Number of times the key has been used |
| `rate_limit` | int(11) | Max requests per hour (NULL = no limit) |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |

### `api_key_usage_log` Table

Tracks all API key usage for monitoring and security purposes.

## Available Permissions

| Permission | Description |
|------------|-------------|
| `webhook_status` | View webhook status and health information |
| `webhook_monitor` | Access webhook monitoring endpoints |
| `webhook_register` | Register/update webhook configurations |
| `system_admin` | Full system administration access |
| `api_manage` | Manage other API keys |
| `*` | All permissions (full access) |

## Migration Guide

### 1. Run the Migration Script

#### Via Command Line (Recommended)
```bash
cd /path/to/bolao3
php scripts/migrate_api_keys.php
```

#### Via Web Interface (Admin Only)
```
https://yourdomain.com/bolao3/scripts/migrate_api_keys.php
```

#### Generate New Admin Key
```bash
php scripts/migrate_api_keys.php --generate-admin-key
```

### 2. Update Your Integrations

#### Old Hardcoded Method
```bash
# This was the old way (still works for backward compatibility)
curl -H "X-API-KEY: webhook_monitor_abcd1234efgh5678" \
     "https://yourdomain.com/api/webhook_status.php"
```

#### New Database Method
```bash
# Use keys generated through the admin interface
curl -H "X-API-KEY: your_new_database_stored_key_here" \
     "https://yourdomain.com/api/webhook_status.php"
```

## Usage Examples

### Creating API Keys (Admin Interface)

1. Navigate to `/admin/api-keys.php`
2. Click "Create New API Key"
3. Fill in the form:
   - **Key Name**: Descriptive name (e.g., "Monitoring System")
   - **Permissions**: Select required permissions
   - **Expires At**: Optional expiration date
   - **Rate Limit**: Optional requests per hour limit
4. Save the generated key securely (it's only shown once)

### Using API Keys

#### In HTTP Headers
```bash
curl -H "X-API-KEY: your_api_key_here" \
     "https://yourdomain.com/api/webhook_status.php"
```

#### As Query Parameter
```bash
curl "https://yourdomain.com/api/webhook_status.php?api_key=your_api_key_here"
```

### API Response with Key Information

When authenticated with an API key, responses include key information:

```json
{
  "status": "success",
  "timestamp": "2025-01-01T12:00:00+00:00",
  "auth_method": "api_key",
  "api_key_info": {
    "key_name": "Monitoring System",
    "key_prefix": "monitor_a1b2_",
    "permissions": ["webhook_status", "webhook_monitor"]
  },
  "data": {
    // ... rest of the response
  }
}
```

## Programming with the ApiKeyManager Class

### Basic Usage

```php
require_once 'includes/classes/ApiKeyManager.php';

$apiKeyManager = new ApiKeyManager();

// Validate an API key
$validation = $apiKeyManager->validateApiKey(
    $apiKey, 
    'webhook_status', // Required permission
    'my_endpoint.php' // Current endpoint for logging
);

if ($validation['valid']) {
    // Key is valid, proceed with request
    $keyInfo = $validation;
} else {
    // Key is invalid
    $error = $validation['error'];
}
```

### Generate New Keys

```php
$result = $apiKeyManager->generateApiKey(
    'My Integration',           // Key name
    ['webhook_status'],         // Permissions
    $userId,                    // Created by user ID
    '2025-12-31 23:59:59',     // Optional expiration
    1000                        // Optional rate limit (per hour)
);

if ($result['success']) {
    $apiKey = $result['api_key']; // Store this securely!
    $keyId = $result['key_id'];
} else {
    $error = $result['error'];
}
```

### Revoke Keys

```php
$success = $apiKeyManager->revokeApiKey($keyId);
```

### Get Usage Statistics

```php
// Get stats for all keys (last 30 days)
$stats = $apiKeyManager->getUsageStats();

// Get stats for specific key (last 7 days)
$stats = $apiKeyManager->getUsageStats($keyId, 7);
```

## Security Features

### Key Storage
- API keys are stored as SHA-256 hashes in the database
- Original keys are never stored in plain text
- Keys are only displayed once when created

### Rate Limiting
- Optional per-key rate limiting (requests per hour)
- Automatic blocking when limits are exceeded
- Usage tracking for monitoring

### Permission System
- Granular permissions for different operations
- Keys can only access endpoints they have permission for
- Admin keys can have wildcard (`*`) permissions

### Audit Logging
- All API key usage is logged with IP addresses and timestamps
- Failed authentication attempts are recorded
- Usage statistics are available in the admin interface

### Expiration and Revocation
- Keys can have optional expiration dates
- Immediate revocation capability
- Expired keys are automatically rejected

## Admin Interface Features

### Dashboard
- Overview of all API keys (active/inactive)
- Usage statistics and summaries
- Quick actions for common tasks

### Key Management
- Create new keys with custom permissions
- View key details and usage history
- Revoke keys instantly
- Filter and search functionality

### Maintenance
- Cleanup old usage logs
- View system statistics
- Export usage reports

## Troubleshooting

### Common Issues

#### "Invalid or expired API key" Error
- Check that the key is still active in the admin interface
- Verify the key hasn't expired
- Ensure you're using the complete key value

#### "Insufficient permissions" Error
- Check that your key has the required permission for the endpoint
- View key details in the admin interface to see available permissions
- Create a new key with appropriate permissions if needed

#### "Rate limit exceeded" Error
- Check the rate limit settings for your key
- Wait for the rate limit window to reset (1 hour)
- Contact admin to increase rate limits if needed

### Checking Key Status

Use the webhook status API to verify your key:

```bash
curl -H "X-API-KEY: your_key" \
     "https://yourdomain.com/api/webhook_status.php?details=true"
```

This will show your key information and current permissions.

## Migration Considerations

### Backward Compatibility
- Old hardcoded keys continue to work during transition period
- Default keys are created automatically during migration
- Existing integrations don't need immediate updates

### Performance Impact
- Database lookups add minimal overhead
- Keys are validated efficiently with indexed queries
- Usage logging is asynchronous and non-blocking

### Security Improvements
- No more hardcoded secrets in source code
- Granular permission control
- Comprehensive audit trails
- Easy key rotation and revocation

## Best Practices

1. **Use descriptive key names** - Makes management easier
2. **Assign minimal permissions** - Follow principle of least privilege  
3. **Set expiration dates for temporary access** - Reduces security risk
4. **Monitor usage regularly** - Check for unusual activity
5. **Rotate keys periodically** - Create new keys and revoke old ones
6. **Use rate limits for external integrations** - Prevents abuse
7. **Keep keys secure** - Never commit keys to version control
8. **Log key creation and revocation** - Maintain security audit trail

## Support

For questions or issues with the API key system:

1. Check the admin interface at `/admin/api-keys.php`
2. Review usage logs for debugging
3. Run the migration script again if needed
4. Check the application logs for detailed error messages

The enhanced API key system provides robust security and management capabilities while maintaining backward compatibility with existing integrations.