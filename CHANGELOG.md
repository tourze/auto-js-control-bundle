# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Complete documentation suite including README, API docs, deployment guide
- Auto.js client implementation examples and best practices
- Comprehensive configuration reference
- Command-line tools documentation

## [1.0.0] - 2025-01-09

### Added
- Initial release of Auto.js Control Bundle
- Device management system with registration and authentication
- HTTP long polling for reliable device communication
- Script distribution and remote execution capabilities
- Task scheduling with immediate, scheduled, and recurring execution
- Real-time device monitoring and status tracking
- Comprehensive logging system with device log collection
- Redis-based instruction queue with priority support
- RESTful API for device and management operations
- Console commands for device and task management
- Event system for extensibility
- Security features including PKI authentication and HMAC signatures

### Features
- **Device Management**
  - Device registration with certificate-based authentication
  - Device grouping and batch operations
  - Automatic offline detection and cleanup
  - Device status monitoring

- **Script Management**
  - JavaScript and project script support
  - Script versioning and validation
  - Secure script distribution
  - Execution result tracking

- **Task System**
  - Flexible task scheduling
  - Priority-based execution
  - Retry mechanism with exponential backoff
  - Task status tracking and history

- **Communication**
  - HTTP long polling optimized for mobile networks
  - Automatic reconnection handling
  - Batch instruction delivery
  - Response caching for performance

- **Security**
  - PKI-based device certificates
  - HMAC-SHA256 request signatures
  - Timestamp validation to prevent replay attacks
  - Rate limiting and IP restrictions

- **Monitoring**
  - Real-time queue monitoring
  - Device performance metrics
  - System health checks
  - Comprehensive audit logging

### Technical Details
- PHP 8.1+ with strict typing
- Symfony 6.4+ compatibility
- Doctrine ORM for data persistence
- Redis for queuing and caching
- PSR-4 autoloading
- PHPUnit test coverage
- PHPStan level 5 compliance

## [0.1.0] - 2024-12-01 (Pre-release)

### Added
- Initial project structure
- Basic entity definitions
- Repository interfaces
- Service skeletons

---

## Upgrade Guide

### From 0.x to 1.0.0

1. **Database Migration Required**
   ```bash
   php bin/console doctrine:migrations:migrate
   ```

2. **Configuration Changes**
   - Update `config/packages/auto_js_control.yaml` with new security settings
   - Add Redis configuration for instruction queues

3. **API Changes**
   - Device registration endpoint moved from `/api/device/register` to `/api/autojs/v1/device/register`
   - All API endpoints now require HMAC signatures

4. **Breaking Changes**
   - Removed WebSocket support in favor of HTTP long polling
   - Changed device authentication from JWT to certificate-based
   - Renamed several entity properties for consistency

## Deprecated Features

### Version 1.0.0
- WebSocket communication (removed) - Use HTTP long polling instead
- JWT authentication (removed) - Use certificate-based authentication
- Legacy API endpoints (removed) - Use versioned API endpoints

## Security Notes

### Version 1.0.0
- All API communications must use HTTPS
- Device certificates expire after 1 year by default
- Implement rate limiting to prevent abuse
- Regular security audits recommended

## Known Issues

### Version 1.0.0
- Script execution timeout may not work correctly on some Auto.js versions
- Large script uploads (>1MB) may timeout on slow connections
- Device cleanup command may be slow with >10000 devices

## Future Plans

### Version 1.1.0 (Planned)
- WebSocket support as optional transport
- GraphQL API endpoint
- Advanced script debugging capabilities
- Multi-tenant support
- Kubernetes operator for deployment

### Version 1.2.0 (Planned)
- Machine learning for anomaly detection
- Advanced scheduling with cron expressions
- Script marketplace integration
- Mobile app for management

## Contributors

- Initial development by Tourze Team
- Security review by Security Team
- Documentation by DevRel Team

## Support

For bugs and feature requests, please use the [issue tracker](https://github.com/tourze/php-monorepo/issues).

For security issues, please email security@tourze.com directly.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.