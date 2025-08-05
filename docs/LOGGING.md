# Logging System Documentation

## Overview

The application now uses a configurable logging system that replaces the excessive `error_log()` calls with structured, level-based logging. This system allows for better control over log output in different environments.

## Log Levels

The logging system supports 5 levels (in order of severity):

- **ERROR**: Critical errors that need immediate attention
- **WARN**: Warning conditions that should be investigated
- **INFO**: General information about application flow
- **DEBUG**: Detailed debug information (development only)
- **TRACE**: Very detailed trace information (development only)

## Configuration

### Environment Variables

Set the log level using environment variables:

```bash
# Production - minimal logging
LOG_LEVEL=INFO

# Development - verbose logging  
LOG_LEVEL=DEBUG

# Emergency only
LOG_LEVEL=ERROR
```

### Configuration Constants

You can also set logging in your configuration files:

```php
// In config/config.php or environment-specific config
define('LOG_LEVEL', 'INFO');  // Production
define('LOG_LEVEL', 'DEBUG'); // Development
define('LOG_FILE', ROOT_DIR . '/logs/app.log');
```

### Default Behavior

- **Development** (`DEBUG_MODE = true`): `LOG_LEVEL = DEBUG`
- **Production** (`DEBUG_MODE = false`): `LOG_LEVEL = INFO`

## Usage

### Basic Logging Functions

```php
// Error logging
log_error("Payment failed", ['user_id' => 123, 'amount' => 50.00]);

// Warning logging
log_warn("API rate limit approaching", ['endpoint' => '/api/payments']);

// Info logging
log_info("User logged in", ['user_id' => 123, 'ip' => '192.168.1.1']);

// Debug logging (only shown in development)
log_debug("Processing payment", ['payment_data' => $data]);

// Trace logging (only shown in development)
log_trace("CURL request details", ['verbose_log' => $curlLog]);
```

### Context Data

All logging functions accept an optional context array for structured data:

```php
log_error("Database connection failed", [
    'host' => $dbHost,
    'database' => $dbName,
    'error_code' => $errorCode,
    'user_id' => getCurrentUserId()
]);
```

### Automatic Context

The logger automatically includes:
- User ID (if available)
- Request method and URI
- IP address
- Session ID
- Environment information

## File Structure

```
logs/
├── app.log          # Main application log
├── app_dev.log      # Development log (if configured)
├── php_errors.log   # PHP error log (existing)
└── payment.log      # Payment-specific log (existing)
```

## Examples

### Before (excessive error_log)
```php
error_log("EFIPIX DEBUG - Iniciando createCharge para user_id: $user_id, valor: $valor");
error_log("EFIPIX DEBUG - Buscando conta para user_id: $user_id");
error_log("EFIPIX DEBUG - Conta encontrada: " . json_encode($conta));
```

### After (structured logging)
```php
log_debug("Iniciando createCharge", [
    'user_id' => $user_id,
    'valor' => $valor
]);
log_debug("Buscando conta", ['user_id' => $user_id]);
log_debug("Conta encontrada", ['conta' => $conta]);
```

## Benefits

1. **Environment Control**: Different log levels for different environments
2. **Structured Data**: JSON-formatted context for better parsing
3. **Performance**: Reduced log output in production
4. **Debugging**: Rich context information for troubleshooting
5. **Maintainability**: Consistent logging format across the application

## Migration Guide

### Replacing error_log() calls:

1. **Error conditions**: Use `log_error()`
2. **Warnings**: Use `log_warn()`
3. **General info**: Use `log_info()`
4. **Debug info**: Use `log_debug()`
5. **Very detailed info**: Use `log_trace()`

### Example migration:

```php
// Old way
error_log("ERRO: Certificado não encontrado em: " . EFI_CERTIFICATE_PATH);

// New way
log_error("Certificado não encontrado", ['path' => EFI_CERTIFICATE_PATH]);
```

## Monitoring

### Production Monitoring

In production, monitor these log levels:
- **ERROR**: Immediate attention required
- **WARN**: Investigate potential issues
- **INFO**: General application health

### Development Debugging

In development, you can see:
- **DEBUG**: Detailed flow information
- **TRACE**: Very detailed technical information

## Best Practices

1. **Use appropriate levels**: Don't use DEBUG in production
2. **Include context**: Always provide relevant data
3. **Be specific**: Clear, descriptive messages
4. **Avoid sensitive data**: Don't log passwords, tokens, etc.
5. **Consistent format**: Use the same message structure

## Troubleshooting

### No logs appearing
- Check log file permissions
- Verify LOG_LEVEL configuration
- Ensure logs directory exists

### Too many logs
- Increase LOG_LEVEL (ERROR, WARN, INFO, DEBUG, TRACE)
- Use environment variables to override

### Missing context
- Ensure Logger class is included
- Check that convenience functions are available 