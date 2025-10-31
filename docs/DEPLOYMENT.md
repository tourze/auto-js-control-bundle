# Deployment Guide

This guide covers deploying the Auto.js Control Bundle in production environments.

## Prerequisites

- Linux server (Ubuntu 20.04+ or CentOS 8+ recommended)
- PHP 8.1+ with required extensions
- MySQL 8.0+ or PostgreSQL 13+
- Redis 6.0+
- Nginx or Apache web server
- SSL certificate for HTTPS
- Composer 2.0+

## System Requirements

### Minimum Requirements

- **CPU**: 2 cores
- **RAM**: 4GB
- **Storage**: 20GB SSD
- **Network**: 100Mbps

### Recommended for Production

- **CPU**: 4+ cores
- **RAM**: 8GB+
- **Storage**: 100GB+ SSD
- **Network**: 1Gbps

### Scaling Guidelines

- **< 100 devices**: Minimum requirements
- **100-1000 devices**: 4 cores, 8GB RAM
- **1000-10000 devices**: 8 cores, 16GB RAM, consider clustering
- **> 10000 devices**: Multiple servers with load balancing

## Installation Steps

### 1. Server Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y php8.1-fpm php8.1-cli php8.1-common \
    php8.1-mysql php8.1-mbstring php8.1-xml php8.1-curl \
    php8.1-zip php8.1-redis php8.1-intl php8.1-bcmath \
    nginx mysql-server redis-server supervisor git

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Create Application User

```bash
# Create dedicated user
sudo useradd -m -s /bin/bash autojs
sudo usermod -aG www-data autojs

# Create application directory
sudo mkdir -p /var/www/autojs
sudo chown autojs:autojs /var/www/autojs
```

### 3. Deploy Application

```bash
# Switch to application user
sudo su - autojs
cd /var/www/autojs

# Clone or copy your application
git clone https://github.com/your-org/your-app.git .
# Or use deployment tools like Deployer, Capistrano, etc.

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
chmod -R 755 var/
chmod -R 755 public/
```

### 4. Environment Configuration

Create `.env.local`:

```bash
# Application
APP_ENV=prod
APP_SECRET=your-secret-key

# Database
DATABASE_URL="mysql://autojs:password@localhost:3306/autojs_prod"

# Redis
REDIS_DSN="redis://localhost:6379"

# Auto.js Control
AUTOJS_API_KEY=your-api-key

# Mailer (for alerts)
MAILER_DSN=smtp://localhost:25
```

### 5. Database Setup

```bash
# Create database
mysql -u root -p <<EOF
CREATE DATABASE autojs_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'autojs'@'localhost' IDENTIFIED BY 'strong-password';
GRANT ALL PRIVILEGES ON autojs_prod.* TO 'autojs'@'localhost';
FLUSH PRIVILEGES;
EOF

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction
```

### 6. Web Server Configuration

#### Nginx Configuration

```nginx
# /etc/nginx/sites-available/autojs
server {
    listen 80;
    server_name autojs.example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name autojs.example.com;
    root /var/www/autojs/public;

    ssl_certificate /etc/ssl/certs/autojs.crt;
    ssl_certificate_key /etc/ssl/private/autojs.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    # API rate limiting
    location /api/autojs/ {
        limit_req zone=autojs_api burst=20 nodelay;
        try_files $uri /index.php$is_args$args;
    }

    error_log /var/log/nginx/autojs_error.log;
    access_log /var/log/nginx/autojs_access.log;
}

# Rate limiting zone
limit_req_zone $binary_remote_addr zone=autojs_api:10m rate=10r/s;
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/autojs /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 7. Process Management

#### Supervisor Configuration

Create supervisor configs for background workers:

```ini
# /etc/supervisor/conf.d/autojs-messenger.conf
[program:autojs-messenger]
command=php /var/www/autojs/bin/console messenger:consume async --time-limit=3600
user=autojs
numprocs=2
autostart=true
autorestart=true
startsecs=0
redirect_stderr=true
stdout_logfile=/var/log/supervisor/autojs-messenger.log
```

```ini
# /etc/supervisor/conf.d/autojs-scheduler.conf
[program:autojs-scheduler]
command=php /var/www/autojs/bin/console autojs:scheduler:run
user=autojs
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/supervisor/autojs-scheduler.log
```

Start supervisors:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 8. Cron Jobs

Add cron jobs for maintenance tasks:

```bash
# Edit crontab for autojs user
sudo -u autojs crontab -e

# Add these lines:
# Clean up offline devices daily at 2 AM
0 2 * * * /usr/bin/php /var/www/autojs/bin/console autojs:device:cleanup --days=30 --force

# Monitor queue health every 5 minutes
*/5 * * * * /usr/bin/php /var/www/autojs/bin/console autojs:queue:monitor --check-health

# Rotate logs weekly
0 0 * * 0 /usr/bin/php /var/www/autojs/bin/console autojs:logs:rotate
```

## Performance Optimization

### 1. PHP-FPM Tuning

Edit `/etc/php/8.1/fpm/pool.d/www.conf`:

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; Enable slow log
slowlog = /var/log/php-fpm/www-slow.log
request_slowlog_timeout = 10s
```

### 2. Redis Optimization

Edit `/etc/redis/redis.conf`:

```conf
# Persistence (choose one)
# Option 1: RDB snapshots (faster, less durable)
save 900 1
save 300 10
save 60 10000

# Option 2: AOF (slower, more durable)
appendonly yes
appendfsync everysec

# Memory management
maxmemory 2gb
maxmemory-policy allkeys-lru

# Connection limits
maxclients 10000
```

### 3. MySQL Optimization

Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
# InnoDB settings
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache
query_cache_type = 1
query_cache_size = 128M

# Connections
max_connections = 200
```

### 4. OPcache Configuration

Edit `/etc/php/8.1/fpm/conf.d/10-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.preload=/var/www/autojs/config/preload.php
opcache.preload_user=autojs
```

## Monitoring

### 1. Application Monitoring

Install monitoring stack:

```bash
# Prometheus + Grafana
docker-compose -f monitoring/docker-compose.yml up -d

# Configure Symfony to export metrics
composer require symfony/prometheus-bundle
```

### 2. Log Aggregation

```bash
# Install Elasticsearch, Logstash, Kibana (ELK)
# Or use cloud services like AWS CloudWatch, Google Stackdriver
```

### 3. Health Checks

Create health check endpoint:

```php
// src/Controller/HealthController.php
class HealthController
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'disk_space' => $this->checkDiskSpace(),
        ];
        
        $healthy = !in_array(false, $checks, true);
        
        return new JsonResponse([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => time(),
        ], $healthy ? 200 : 503);
    }
}
```

## Security Hardening

### 1. Firewall Configuration

```bash
# Install UFW
sudo apt install ufw

# Configure firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 2. Fail2ban Configuration

```bash
# Install fail2ban
sudo apt install fail2ban

# Create jail for Auto.js API
sudo nano /etc/fail2ban/jail.local
```

```ini
[autojs-api]
enabled = true
port = http,https
filter = autojs-api
logpath = /var/log/nginx/autojs_access.log
maxretry = 10
findtime = 600
bantime = 3600
```

### 3. SSL/TLS Configuration

Use Let's Encrypt for free SSL certificates:

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d autojs.example.com
```

## Backup Strategy

### 1. Database Backups

```bash
# Daily backup script
#!/bin/bash
BACKUP_DIR="/var/backups/autojs"
DATE=$(date +%Y%m%d_%H%M%S)

# Database backup
mysqldump -u autojs -p'password' autojs_prod | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Keep only last 7 days
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +7 -delete
```

### 2. File Backups

```bash
# Backup uploaded files and logs
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/autojs/var/
```

### 3. Redis Backups

```bash
# Force Redis to save
redis-cli BGSAVE

# Copy dump file
cp /var/lib/redis/dump.rdb $BACKUP_DIR/redis_$DATE.rdb
```

## Scaling Strategies

### 1. Horizontal Scaling

For high device counts, use multiple application servers:

```
                    ┌─────────────┐
                    │ Load        │
                    │ Balancer    │
                    └──────┬──────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
┌───────▼───────┐  ┌───────▼───────┐  ┌───────▼───────┐
│ App Server 1  │  │ App Server 2  │  │ App Server 3  │
└───────┬───────┘  └───────┬───────┘  └───────┬───────┘
        │                  │                  │
        └──────────────────┼──────────────────┘
                           │
                    ┌──────▼──────┐
                    │   Redis     │
                    │  Cluster    │
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │   MySQL     │
                    │  Primary/   │
                    │  Replica    │
                    └─────────────┘
```

### 2. Redis Clustering

```bash
# Configure Redis Cluster for high availability
redis-cli --cluster create \
    192.168.1.1:7001 \
    192.168.1.2:7002 \
    192.168.1.3:7003 \
    --cluster-replicas 1
```

### 3. Database Replication

Set up MySQL master-slave replication for read scaling:

```sql
-- On master
CREATE USER 'replication'@'%' IDENTIFIED BY 'password';
GRANT REPLICATION SLAVE ON *.* TO 'replication'@'%';
```

## Troubleshooting

### Common Issues

1. **High Memory Usage**
   - Check PHP-FPM process count
   - Review Redis memory usage
   - Enable swap as emergency measure

2. **Slow API Response**
   - Check slow query log
   - Review PHP-FPM slow log
   - Monitor Redis latency

3. **Device Connection Issues**
   - Check firewall rules
   - Verify SSL certificates
   - Review nginx rate limiting

### Debug Mode

Enable debug mode temporarily:

```bash
# Enable debug logging
export APP_ENV=dev
export APP_DEBUG=1

# Check application logs
tail -f var/log/dev.log

# Monitor system logs
journalctl -f -u nginx -u php8.1-fpm
```

## Maintenance

### Regular Tasks

1. **Weekly**
   - Review error logs
   - Check disk usage
   - Update security patches

2. **Monthly**
   - Review performance metrics
   - Clean old logs and data
   - Test backup restoration

3. **Quarterly**
   - Update dependencies
   - Review security configuration
   - Performance optimization

### Upgrade Procedure

```bash
# 1. Backup everything
/var/backups/autojs/backup-all.sh

# 2. Put site in maintenance mode
php bin/console app:maintenance:enable

# 3. Update code
git pull origin main
composer install --no-dev --optimize-autoloader

# 4. Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Clear caches
php bin/console cache:clear --env=prod

# 6. Restart services
sudo systemctl restart php8.1-fpm
sudo supervisorctl restart all

# 7. Disable maintenance mode
php bin/console app:maintenance:disable
```

## Support

For production support:

1. Check system status: `php bin/console autojs:system:status`
2. Review logs in `/var/log/`
3. Monitor metrics in Grafana dashboard
4. Contact support with diagnostic bundle: `php bin/console autojs:diagnostic:create`