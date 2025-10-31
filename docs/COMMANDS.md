# Auto.js Control Bundle Commands

This document describes all available console commands provided by the Auto.js Control Bundle.

## Device Management Commands

### autojs:device:list

List all registered devices with their current status.

```bash
php bin/console autojs:device:list [options]
```

**Options:**
- `--status=<status>` - Filter by device status (online, offline, all)
- `--group=<group-id>` - Filter by device group
- `--sort=<field>` - Sort by field (name, code, last_seen, created_at)
- `--limit=<number>` - Limit number of results (default: 50)

**Examples:**
```bash
# List all online devices
php bin/console autojs:device:list --status=online

# List devices in a specific group
php bin/console autojs:device:list --group=5

# List recently active devices
php bin/console autojs:device:list --sort=last_seen --limit=10
```

### autojs:device:cleanup

Clean up offline devices that haven't been seen for a specified period.

```bash
php bin/console autojs:device:cleanup [options]
```

**Options:**
- `--days=<number>` - Days of inactivity before cleanup (default: 30)
- `--dry-run` - Show what would be deleted without actually deleting
- `--force` - Skip confirmation prompt

**Examples:**
```bash
# Preview cleanup of devices offline for 30+ days
php bin/console autojs:device:cleanup --dry-run

# Clean up devices offline for 60+ days
php bin/console autojs:device:cleanup --days=60 --force
```

### autojs:device:simulator

Start device simulators for testing purposes.

```bash
php bin/console autojs:device:simulator <device-count> [options]
```

**Arguments:**
- `device-count` - Number of simulated devices to create

**Options:**
- `--prefix=<string>` - Device code prefix (default: "SIM")
- `--interval=<seconds>` - Heartbeat interval (default: 30)
- `--execute-scripts` - Enable script execution simulation

**Examples:**
```bash
# Start 10 device simulators
php bin/console autojs:device:simulator 10

# Start 5 simulators with custom prefix and faster heartbeat
php bin/console autojs:device:simulator 5 --prefix=TEST --interval=10
```

## Task Management Commands

### autojs:task:execute

Execute a task immediately on specified devices.

```bash
php bin/console autojs:task:execute <task-id> [options]
```

**Arguments:**
- `task-id` - The ID of the task to execute

**Options:**
- `--device=<device-code>` - Target specific device(s) (can be used multiple times)
- `--group=<group-id>` - Target all devices in a group
- `--priority=<1-10>` - Execution priority (default: 5)
- `--timeout=<seconds>` - Execution timeout (default: 300)

**Examples:**
```bash
# Execute task on all eligible devices
php bin/console autojs:task:execute 123

# Execute task on specific devices
php bin/console autojs:task:execute 123 --device=DEVICE-001 --device=DEVICE-002

# Execute task on a device group with high priority
php bin/console autojs:task:execute 123 --group=5 --priority=9
```

## Script Management Commands

### autojs:script:validate

Validate a script for syntax errors and security issues.

```bash
php bin/console autojs:script:validate <script-id>
```

**Arguments:**
- `script-id` - The ID of the script to validate

**Examples:**
```bash
# Validate script with ID 45
php bin/console autojs:script:validate 45
```

## Monitoring Commands

### autojs:queue:monitor

Monitor the instruction queue in real-time.

```bash
php bin/console autojs:queue:monitor [options]
```

**Options:**
- `--interval=<seconds>` - Refresh interval (default: 5)
- `--device=<device-code>` - Monitor specific device queue
- `--show-instructions` - Show detailed instruction information

**Examples:**
```bash
# Monitor all queues with default settings
php bin/console autojs:queue:monitor

# Monitor specific device queue with details
php bin/console autojs:queue:monitor --device=DEVICE-001 --show-instructions

# Fast refresh monitoring
php bin/console autojs:queue:monitor --interval=1
```

## Common Options

All commands support these common options:

- `-h, --help` - Display help for the command
- `-q, --quiet` - Do not output any message
- `-v|vv|vvv, --verbose` - Increase verbosity (1: normal, 2: verbose, 3: debug)
- `--ansi` - Force ANSI output
- `--no-ansi` - Disable ANSI output
- `-n, --no-interaction` - Do not ask any interactive question

## Exit Codes

Commands use standard exit codes:
- `0` - Success
- `1` - General error
- `2` - Invalid input/arguments
- `3` - Runtime error

## Scheduled Commands

Some commands are designed to be run via cron or scheduler:

```bash
# Clean up offline devices daily
0 2 * * * /usr/bin/php /path/to/bin/console autojs:device:cleanup --days=30 --force

# Monitor queue health every 5 minutes
*/5 * * * * /usr/bin/php /path/to/bin/console autojs:queue:monitor --interval=0
```

## Debugging

Use verbosity levels for debugging:

```bash
# Normal output
php bin/console autojs:device:list

# Verbose output (shows SQL queries)
php bin/console autojs:device:list -v

# Very verbose (shows debug information)
php bin/console autojs:device:list -vv

# Debug output (shows all internal operations)
php bin/console autojs:device:list -vvv
```

## Custom Commands

You can create custom commands by extending the bundle's base command class:

```php
namespace App\Command;

use Tourze\AutoJsControlBundle\Command\AbstractAutoJsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomAutoJsCommand extends AbstractAutoJsCommand
{
    protected static $defaultName = 'app:autojs:custom';
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Your custom logic here
        return self::SUCCESS;
    }
}
```