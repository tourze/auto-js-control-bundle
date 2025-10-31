# Configuration Reference

This document provides a complete reference for configuring the Auto.js Control Bundle.

## Full Configuration Example

```yaml
# config/packages/auto_js_control.yaml
auto_js_control:
    # Redis configuration
    redis:
        dsn: '%env(REDIS_DSN)%'  # Default: redis://localhost:6379
        options:
            prefix: 'autojs:'      # Key prefix for all Redis keys
            connection_timeout: 5  # Connection timeout in seconds
            read_timeout: 10       # Read timeout for blocking operations
            retry_interval: 100    # Retry interval in milliseconds
            retry_count: 3         # Number of retries on failure

    # Security settings
    security:
        api_key: '%env(AUTOJS_API_KEY)%'  # Required: API key for authentication
        signature_algorithm: 'sha256'       # Algorithm for HMAC signatures
        timestamp_tolerance: 300            # Max time difference in seconds (5 min)
        certificate_lifetime: 31536000      # Certificate validity in seconds (1 year)
        enable_rate_limiting: true          # Enable API rate limiting
        rate_limit:
            requests_per_minute: 60         # Max requests per device per minute
            burst_size: 10                  # Allow burst of requests

    # HTTP polling configuration
    polling:
        timeout: 30                # Default polling timeout in seconds
        max_timeout: 60           # Maximum allowed polling timeout
        min_timeout: 5            # Minimum allowed polling timeout
        queue_wait_timeout: 1     # Redis BRPOP timeout for each iteration

    # Device management
    device:
        offline_threshold: 300     # Seconds before marking device offline (5 min)
        cleanup_interval: 3600     # Interval for cleanup job in seconds (1 hour)
        cleanup_after_days: 30     # Days of inactivity before device removal
        max_devices_per_group: 1000  # Maximum devices allowed per group
        heartbeat_interval: 30     # Expected heartbeat interval in seconds
        enable_monitoring: true    # Enable device monitoring features

    # Task execution
    task:
        default_timeout: 300       # Default script execution timeout (5 min)
        max_timeout: 3600         # Maximum allowed timeout (1 hour)
        retry_attempts: 3         # Number of retry attempts on failure
        retry_delay: 60           # Delay between retries in seconds
        priority_levels: 10       # Number of priority levels (1-10)
        enable_scheduling: true   # Enable task scheduling features

    # Script management
    script:
        max_size: 1048576         # Maximum script size in bytes (1MB)
        allowed_types:            # Allowed script types
            - javascript
            - project
        enable_validation: true   # Enable script validation
        sandbox_timeout: 30       # Sandbox validation timeout
        cache_ttl: 3600          # Script cache TTL in seconds

    # Logging configuration
    logging:
        enable_device_logs: true  # Enable device log collection
        log_retention_days: 30    # Days to retain device logs
        max_log_size: 10485760   # Max log upload size (10MB)
        log_levels:              # Allowed log levels
            - DEBUG
            - INFO
            - WARNING
            - ERROR
            - CRITICAL

    # Performance tuning
    performance:
        enable_caching: true      # Enable response caching
        cache_ttl: 300           # Cache TTL in seconds
        batch_size: 100          # Batch processing size
        worker_processes: 4      # Number of worker processes
        queue_workers: 2         # Number of queue workers

    # Feature flags
    features:
        enable_websocket: false   # Enable WebSocket support (experimental)
        enable_metrics: true      # Enable metrics collection
        enable_audit_log: true    # Enable audit logging
        enable_encryption: true   # Enable end-to-end encryption
```

## Configuration Sections

### Redis Configuration

The Redis section configures the connection to Redis server used for queuing and caching.

```yaml
redis:
    dsn: 'redis://localhost:6379/0'
    options:
        prefix: 'autojs:'
        persistent: true          # Use persistent connections
        serializer: 'php'        # Options: php, json, igbinary
```

**Environment Variables:**
```bash
REDIS_DSN=redis://localhost:6379
# or with authentication
REDIS_DSN=redis://username:password@localhost:6379/0
```

### Security Configuration

Critical security settings for device authentication and API access.

```yaml
security:
    api_key: '%env(AUTOJS_API_KEY)%'
    signature_algorithm: 'sha256'    # Options: sha256, sha512
    timestamp_tolerance: 300
    
    # Certificate settings
    certificate_lifetime: 31536000   # 1 year
    certificate_algorithm: 'RS256'   # JWT algorithm
    
    # IP restrictions (optional)
    allowed_ips:
        - '192.168.1.0/24'
        - '10.0.0.0/8'
    
    # Device restrictions
    max_devices_per_ip: 100
    require_device_approval: false
```

### Polling Configuration

Controls the HTTP long polling behavior.

```yaml
polling:
    timeout: 30                     # Client timeout
    max_timeout: 60                # Server enforced max
    
    # Advanced settings
    keep_alive: true               # Enable HTTP keep-alive
    compression: true              # Enable response compression
    batch_instructions: true       # Send multiple instructions per response
    max_instructions_per_poll: 10  # Limit instructions per response
```

### Device Management

Settings for device lifecycle and monitoring.

```yaml
device:
    # Status thresholds
    offline_threshold: 300         # 5 minutes
    warning_threshold: 180         # 3 minutes
    
    # Cleanup settings
    cleanup_interval: 3600         # Run every hour
    cleanup_after_days: 30
    cleanup_batch_size: 100
    
    # Monitoring
    enable_monitoring: true
    monitor_interval: 60           # Check every minute
    alert_on_offline: true
    alert_channels:
        - email
        - slack
```

### Task Configuration

Task execution and scheduling settings.

```yaml
task:
    # Execution settings
    default_timeout: 300
    max_timeout: 3600
    kill_timeout: 30              # Grace period before force kill
    
    # Retry policy
    retry_attempts: 3
    retry_delay: 60
    exponential_backoff: true
    max_retry_delay: 3600
    
    # Scheduling
    enable_scheduling: true
    scheduler_timezone: 'UTC'
    max_scheduled_tasks: 1000
    
    # Priority queue
    priority_levels: 10
    default_priority: 5
    high_priority_threshold: 8
```

### Script Management

Script storage and validation settings.

```yaml
script:
    # Storage
    storage_path: '%kernel.project_dir%/var/scripts'
    max_size: 1048576            # 1MB
    
    # Validation
    enable_validation: true
    validation_rules:
        - no_eval
        - no_file_system
        - no_network
    
    # Versioning
    enable_versioning: true
    max_versions: 10
    
    # Compression
    enable_compression: true
    compression_threshold: 1024   # Compress if larger than 1KB
```

### Logging Configuration

Device log collection and retention settings.

```yaml
logging:
    # Collection
    enable_device_logs: true
    log_buffer_size: 1000        # Buffer before flush
    flush_interval: 60           # Flush every minute
    
    # Retention
    log_retention_days: 30
    archive_old_logs: true
    archive_path: '%kernel.project_dir%/var/archives'
    
    # Processing
    enable_log_processing: true
    processors:
        - anonymize_ips
        - detect_errors
        - extract_metrics
```

### Performance Tuning

Advanced performance optimization settings.

```yaml
performance:
    # Caching
    enable_caching: true
    cache_adapter: 'redis'       # Options: redis, apcu, filesystem
    cache_ttl: 300
    
    # Batching
    batch_size: 100
    batch_timeout: 5             # Process batch after 5 seconds
    
    # Concurrency
    worker_processes: 4
    max_connections: 1000
    connection_pool_size: 100
    
    # Memory
    memory_limit: '256M'
    gc_probability: 0.01         # 1% chance of GC per request
```

## Environment-Specific Configuration

### Development

```yaml
# config/packages/dev/auto_js_control.yaml
auto_js_control:
    security:
        timestamp_tolerance: 3600  # More lenient in dev
        enable_rate_limiting: false
    
    logging:
        log_levels:
            - DEBUG
            - INFO
            - WARNING
            - ERROR
            - CRITICAL
    
    performance:
        enable_caching: false     # Disable caching in dev
```

### Production

```yaml
# config/packages/prod/auto_js_control.yaml
auto_js_control:
    security:
        enable_rate_limiting: true
        require_device_approval: true
    
    logging:
        log_levels:
            - WARNING
            - ERROR
            - CRITICAL
    
    performance:
        enable_caching: true
        worker_processes: 8       # More workers in production
```

### Testing

```yaml
# config/packages/test/auto_js_control.yaml
auto_js_control:
    redis:
        dsn: 'redis://localhost:6379/15'  # Separate test database
    
    device:
        cleanup_after_days: 1     # Faster cleanup in tests
    
    performance:
        worker_processes: 1       # Single process for tests
```

## Validation

The bundle validates configuration on boot. Invalid configuration will result in clear error messages:

```
The value "invalid" is not allowed for path "auto_js_control.security.signature_algorithm". 
Permissible values: "sha256", "sha512"
```

## Best Practices

1. **Use Environment Variables**: Store sensitive values like API keys in environment variables
2. **Redis Prefix**: Always use a prefix to avoid key collisions
3. **Monitoring**: Enable monitoring in production for better observability
4. **Rate Limiting**: Always enable rate limiting in production
5. **Logging**: Balance between debugging needs and storage costs
6. **Performance**: Tune worker processes based on your server capacity
7. **Security**: Use the strictest settings that work for your use case

## Troubleshooting

### Redis Connection Issues

```yaml
redis:
    options:
        connection_timeout: 10    # Increase timeout
        retry_count: 5           # More retries
        retry_interval: 500      # Longer retry interval
```

### Memory Issues

```yaml
performance:
    batch_size: 50              # Smaller batches
    memory_limit: '512M'        # Increase memory limit
    gc_probability: 0.1         # More aggressive GC
```

### Slow Polling

```yaml
polling:
    queue_wait_timeout: 0.1     # Faster queue checks
    batch_instructions: true    # Send multiple instructions
performance:
    enable_caching: true        # Cache responses
```