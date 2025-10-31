# Auto.js Control Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://packagist.org/packages/tourze/auto-js-control-bundle)
[![Symfony Version](https://img.shields.io/badge/symfony-%5E7.3-green.svg)](https://packagist.org/packages/tourze/auto-js-control-bundle)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

A comprehensive Symfony bundle for managing and controlling Auto.js devices through HTTP long polling, providing enterprise-grade remote automation capabilities with task scheduling, script distribution, and real-time monitoring.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Usage](#usage)
- [API Reference](#api-reference)
- [Advanced Usage](#advanced-usage)
- [Security](#security)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Device Management**: Register, authenticate, and manage thousands of 
  Auto.js devices
- **Script Distribution**: Deploy and execute JavaScript scripts across 
  device fleets
- **HTTP Long Polling**: Reliable communication optimized for mobile networks
- **Task Scheduling**: Execute tasks immediately, scheduled, or recurring
- **Real-time Monitoring**: Track device status, collect logs, and monitor 
  performance
- **Security First**: PKI-based authentication, encrypted communication, 
  sandboxed execution
- **Scalable Architecture**: Redis-backed queues, distributed deployment ready
- **RESTful API**: Well-documented API for device communication and management

## Requirements

- PHP 8.1 or higher
- Symfony 7.3 or higher
- MySQL/MariaDB or PostgreSQL
- Redis 6.0 or higher
- Composer
- Extensions: ext-redis, ext-json, ext-hash, ext-pcntl

## Installation

Add the bundle to your Symfony project:

```bash
composer require tourze/auto-js-control-bundle
```

Enable the bundle in your `config/bundles.php`:

```php
return [
    // ...
    Tourze\AutoJsControlBundle\AutoJsControlBundle::class => ['all' => true],
];
```

## Configuration

Create a configuration file at `config/packages/auto_js_control.yaml`:

```yaml
auto_js_control:
    redis:
        dsn: '%env(REDIS_DSN)%'
    security:
        api_key: '%env(AUTOJS_API_KEY)%'
        signature_algorithm: 'sha256'
        timestamp_tolerance: 300
    device:
        offline_threshold: 300
        heartbeat_interval: 30
        cleanup_interval: 3600
    task:
        default_timeout: 300
        retry_attempts: 3
        retry_delay: 60
    polling:
        timeout: 30
        max_timeout: 60
```

Required environment variables:

```bash
# .env
REDIS_DSN=redis://localhost:6379
AUTOJS_API_KEY=your-secret-api-key
DATABASE_URL=mysql://user:pass@localhost:3306/dbname
```

## Quick Start

### 1. Database Setup

Run the migrations to create the required database tables:

```bash
php bin/console doctrine:migrations:migrate
```

### 2. Environment Configuration

Configure your environment variables:

```bash
# .env
REDIS_DSN=redis://localhost:6379
DATABASE_URL=mysql://user:pass@localhost:3306/dbname
```

### 3. Register a Device

Register a new Auto.js device:

```bash
php bin/console auto-js:device:register device001 --name="Test Device"
```

### 4. Create and Execute a Script

```php
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Service\ScriptManager;
use Tourze\AutoJsControlBundle\Service\TaskScheduler;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;

$scriptManager = $this->container->get(ScriptManager::class);
$taskScheduler = $this->container->get(TaskScheduler::class);

// Create a script
$script = new Script();
$script->setName('Hello World')
       ->setContent('console.log("Hello from Auto.js!");')
       ->setScriptType(ScriptType::JAVASCRIPT);

$scriptManager->save($script);

// Execute on specific device
$task = $taskScheduler->createAndScheduleTask([
    'name' => 'Hello World Task',
    'taskType' => TaskType::IMMEDIATE->value,
    'targetType' => TaskTargetType::SPECIFIC->value,
    'script' => $script,
    'targetDevices' => ['device001']
]);
```

## Usage

### Device Management

```php
use Tourze\AutoJsControlBundle\Service\DeviceManager;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;

$deviceManager = $this->container->get(DeviceManager::class);
$deviceRepo = $this->container->get(AutoJsDeviceRepository::class);

// Get all online devices
$onlineDevices = $deviceRepo->findAllOnlineDevices();

// Get device by code
$device = $deviceRepo->findByDeviceCode('device001');

// Check device status
if ($device && $device->isOnline()) {
    echo "Device is online";
}
```

### Script Management

```php
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Service\ScriptValidationService;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Doctrine\ORM\EntityManagerInterface;

$entityManager = $this->container->get(EntityManagerInterface::class);
$validationService = $this->container->get(ScriptValidationService::class);

// Create a new script
$script = new Script();
$script->setName('My Script')
       ->setContent('console.log("Hello World");')
       ->setScriptType(ScriptType::JAVASCRIPT)
       ->setTimeout(60);

// Validate script syntax
if ($validationService->validateScript($script)) {
    $entityManager->persist($script);
    $entityManager->flush();
}
```

### Task Scheduling

```php
use Tourze\AutoJsControlBundle\Service\TaskScheduler;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;

$scheduler = $this->container->get(TaskScheduler::class);

// Execute immediately
$task = $scheduler->createAndScheduleTask([
    'name' => 'Immediate Task',
    'script' => $script,
    'taskType' => TaskType::IMMEDIATE->value,
    'targetType' => TaskTargetType::SPECIFIC->value,
    'targetDevices' => ['device001', 'device002']
]);

// Schedule for later
$scheduledTask = $scheduler->createAndScheduleTask([
    'name' => 'Scheduled Task',
    'script' => $script,
    'taskType' => TaskType::SCHEDULED->value,
    'targetType' => TaskTargetType::SPECIFIC->value,
    'scheduledTime' => (new \DateTime('+1 hour'))->format('c'),
    'targetDevices' => ['device001']
]);

// Recurring task
$recurringTask = $scheduler->createAndScheduleTask([
    'name' => 'Daily Task',
    'script' => $script,
    'taskType' => TaskType::RECURRING->value,
    'targetType' => TaskTargetType::SPECIFIC->value,
    'cronExpression' => '0 9 * * *', // Daily at 9 AM
    'targetDevices' => ['device001']
]);
```

### Console Commands

The bundle provides several console commands for management and debugging:

```bash
# Device management
php bin/console auto-js:device:list              # List all registered devices
php bin/console auto-js:device:cleanup           # Clean up offline devices
php bin/console auto-js:device:simulator         # Simulate devices for testing

# Queue monitoring
php bin/console auto-js:queue:monitor            # Monitor instruction queues in real-time

# Script management
php bin/console auto-js:script:validate          # Validate script syntax

# Task execution
php bin/console auto-js:task:execute             # Execute tasks manually
```

## API Reference

### Device API Endpoints

- `POST /api/autojs/v1/device/register` - Register a new device
- `POST /api/autojs/v1/device/heartbeat` - Device heartbeat with long polling
- `POST /api/autojs/v1/device/report-result` - Report script execution results
- `POST /api/autojs/v1/device/logs` - Batch upload device logs
- `GET /api/autojs/v1/device/script/{scriptId}` - Download script content
- `POST /api/autojs/v1/device/screenshot` - Upload execution screenshots

### Management Endpoints

- `GET /api/autojs/v1/devices` - List all devices
- `GET /api/autojs/v1/scripts` - List all scripts
- `POST /api/autojs/v1/scripts` - Create a new script
- `POST /api/autojs/v1/tasks` - Create and schedule a task
- `GET /api/autojs/v1/tasks/{id}/status` - Get task execution status

## Architecture

### System Overview

The Auto.js Control Bundle implements a comprehensive device management system with the following architecture:

```text
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Auto.js       │    │   Symfony        │    │   Redis         │
│   Devices       │◄──►│   Application    │◄──►│   Queue         │
│                 │    │                  │    │                 │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                        │                        │
         │                        │                        │
         ▼                        ▼                        ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   HTTP Long     │    │   Task           │    │   Device        │
│   Polling       │    │   Scheduler      │    │   Monitor       │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### Core Components

#### 1. HTTP Long Polling API
- **Device Registration**: Secure device enrollment with certificate generation
- **Heartbeat Mechanism**: Long polling for real-time instruction delivery
- **Result Reporting**: Asynchronous execution result collection
- **Log Aggregation**: Centralized device log collection

#### 2. Task Management System
- **Script Management**: JavaScript code distribution and versioning
- **Task Scheduling**: Immediate, scheduled, and recurring task execution
- **Priority Queues**: Multi-level task prioritization
- **Execution Tracking**: Comprehensive task status monitoring

#### 3. Device Monitoring
- **Real-time Metrics**: CPU, memory, battery, and network monitoring
- **Health Checks**: Automated device availability detection
- **Log Analysis**: Centralized logging with filtering and search
- **Alert System**: Configurable notifications for device issues

#### 4. Security Framework
- **Certificate-based Authentication**: HMAC-SHA256 signed requests
- **Timestamp Protection**: Anti-replay attack mechanisms
- **Access Control**: Device-specific resource isolation
- **Encrypted Communication**: TLS-only data transmission

### Data Flow

1. **Device Registration**:
    - Device sends registration request with hardware info
    - Server validates request and generates unique certificate
    - Device receives certificate for subsequent authentications

2. **Task Execution Flow**:
    - Admin creates task with target devices/groups
    - TaskScheduler distributes instructions to device queues
    - Devices poll for instructions via long polling
    - Instructions executed on devices with result reporting

3. **Long Polling Mechanism**:
    - Device initiates heartbeat with configurable timeout
    - Server checks instruction queue immediately
    - If no instructions, server waits for Redis pub/sub notifications
    - New instructions trigger immediate response or timeout expires

### Redis Queue Design

#### Queue Structure
```text
device_instruction_queue:{deviceCode}  # Device-specific instruction queue
device_poll_notify:{deviceCode}        # Pub/Sub notification channel
device_online:{deviceCode}             # Online status tracking
instruction_status:{instructionId}     # Execution status tracking
```

#### Queue Operations
- **LPUSH**: Add high-priority instructions to queue front
- **RPUSH**: Add normal instructions to queue rear
- **BRPOP**: Blocking pop for instruction retrieval
- **PUBLISH**: Notify long polling clients of new instructions

## Advanced Usage

### Custom Instruction Types

Create custom instruction types by implementing the instruction interface:

```php
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

// Create custom instruction using the standard DeviceInstruction class
$customInstruction = new DeviceInstruction(
    instructionId: 'CUSTOM-001',
    type: 'custom_action',
    data: [
        'action' => 'custom',
        'parameters' => ['key' => 'value']
    ],
    timeout: 300,
    priority: 5
);
```

### Event Listeners

Listen to device and task events:

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\AutoJsControlBundle\Event\DeviceRegisteredEvent;
use Tourze\AutoJsControlBundle\Event\TaskCreatedEvent;
use Tourze\AutoJsControlBundle\Event\ScriptExecutedEvent;

class AutoJsEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            DeviceRegisteredEvent::class => 'onDeviceRegistered',
            TaskCreatedEvent::class => 'onTaskCreated',
            ScriptExecutedEvent::class => 'onScriptExecuted',
        ];
    }
    
    public function onDeviceRegistered(DeviceRegisteredEvent $event): void
    {
        $device = $event->getDevice();
        // Handle device registration logic
    }
    
    public function onTaskCreated(TaskCreatedEvent $event): void
    {
        $task = $event->getTask();
        // Handle task creation logic
    }
    
    public function onScriptExecuted(ScriptExecutedEvent $event): void
    {
        $executionRecord = $event->getExecutionRecord();
        // Handle script execution completion logic
    }
}
```

### Monitoring and Logging

```php
use Tourze\AutoJsControlBundle\Service\DeviceManager;

use Tourze\AutoJsControlBundle\Repository\DeviceMonitorDataRepository;
use Tourze\AutoJsControlBundle\Repository\DeviceLogRepository;

$monitorRepo = $this->container->get(DeviceMonitorDataRepository::class);
$logRepo = $this->container->get(DeviceLogRepository::class);

// Get latest monitor data for device
$device = $deviceRepo->findByDeviceCode('device001');
if ($device) {
    $monitorData = $monitorRepo->findLatestByDevice($device);
    if ($monitorData) {
        $cpuUsage = $monitorData->getCpuUsage();
        $memoryUsage = $monitorData->getMemoryUsage();
    }
    
    // Query execution logs
    $logs = $logRepo->findRecentByDevice($device, [
        'level' => 'ERROR',
        'since' => new \DateTime('-1 hour')
    ]);
}
```

## Security

### Authentication

Devices authenticate using API keys and device-specific tokens. The bundle 
supports:

- Device registration tokens
- API key authentication
- IP whitelist (optional)
- Request signing

### Sandboxed Execution

Scripts run in a controlled environment with:

- Execution timeouts
- Resource limits
- API access controls
- Security rule validation

### Communication Security

- TLS encryption for all communications
- Message integrity verification
- Replay attack prevention

## Development Guide

### Setting Up Development Environment

1. **Clone and Install Dependencies**:
```bash
git clone <repository-url>
cd php-monorepo
composer install
```

2. **Configure Database**:
```bash
# Create database
bin/console doctrine:database:create

# Run migrations
bin/console doctrine:migrations:migrate
```

3. **Start Redis**:
```bash
# Using Docker
docker run -d -p 6379:6379 redis:6.0-alpine

# Or install locally
sudo systemctl start redis
```

### Development Workflow

1. **Create New Features**:
    - Follow PSR-12 coding standards
    - Create entities in `src/Entity/`
    - Add repositories in `src/Repository/`
    - Implement services in `src/Service/`
    - Add controllers in `src/Controller/`

2. **Database Changes**:
```bash
# Generate migration
bin/console doctrine:migrations:diff

# Review and execute
bin/console doctrine:migrations:migrate
```

3. **Adding Commands**:
```bash
# Generate command
bin/console make:command

# Register in services.yaml if needed
```

### Code Quality Tools

```bash
# PHPStan static analysis
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/auto-js-control-bundle

# Code style fixing
./vendor/bin/php-cs-fixer fix packages/auto-js-control-bundle/src

# Package validation
bin/console app:check-packages auto-js-control-bundle -o -f --skip=github-actions-status
```

### Testing

```bash
# From the project root directory

# Run all tests
./vendor/bin/phpunit packages/auto-js-control-bundle/tests

# Run specific test class
./vendor/bin/phpunit packages/auto-js-control-bundle/tests/Service/TaskSchedulerTest.php

# Run with coverage
./vendor/bin/phpunit packages/auto-js-control-bundle/tests --coverage-html coverage

# Integration tests with real Redis
REDIS_DSN=redis://localhost:6379/15 ./vendor/bin/phpunit packages/auto-js-control-bundle/tests
```

### Debugging

1. **Enable Debug Mode**:
```yaml
# config/packages/dev/auto_js_control.yaml
auto_js_control:
    logging:
        log_levels: [DEBUG, INFO, WARNING, ERROR, CRITICAL]
    performance:
        enable_caching: false
```

2. **Monitor Redis Queues**:
```bash
# Console command for queue monitoring
bin/console auto-js:queue:monitor

# Or using Redis CLI
redis-cli MONITOR
```

3. **Device Simulation**:
```bash
# Simulate devices for testing
bin/console auto-js:device:simulator --count=5 --duration=3600
```

### Performance Optimization

1. **Database Optimization**:
    - Use proper indexes on frequently queried columns
    - Implement query result caching where appropriate
    - Use EXTRA_LAZY loading for large collections

2. **Redis Optimization**:
    - Set appropriate TTL values for keys
    - Use Redis pipelining for batch operations
    - Monitor Redis memory usage

3. **Application Optimization**:
    - Enable OPCache in production
    - Use Symfony's HTTP cache
    - Implement proper logging levels

### Adding New Device Instructions

1. **Define Instruction Type**:
```php
// In DeviceInstruction class
public const TYPE_CUSTOM_ACTION = 'custom_action';
```

2. **Create Handler** (optional):
```php
namespace Tourze\AutoJsControlBundle\Handler;

class CustomActionInstructionHandler implements InstructionHandlerInterface
{
    public function handle(DeviceInstruction $instruction): void
    {
        // Custom handling logic
    }
}
```

3. **Register Handler**:
```yaml
# config/services.yaml
services:
    Tourze\AutoJsControlBundle\Handler\CustomActionInstructionHandler:
        tags:
            - { name: 'auto_js_control.instruction_handler', type: 'custom_action' }
```

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of 
conduct and the process for submitting pull requests.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) 
file for details.