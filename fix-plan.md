# AutoJsControlBundle - Code Quality Fix Plan

## Phase 1: Critical Test Fixes (Batch 1)
1. Fix AutoJsDeviceCrudControllerTest - implement abstract methods
2. Verify tests can run

## Phase 2: Core Service Issues (Batch 2)
1. Fix DeviceRegistrationService - inject repositories properly
2. Fix DeviceReportService - foreach argument and base64_decode issues
3. Fix InstructionQueueService - missing iterable value types

## Phase 3: Additional Static Analysis Issues (Batch 3)
1. Fix MonitoringCoordinator - parameter type issues
2. Fix all other Doctrine repository injection issues
3. Fix missing return type in ScriptCrudService::saveScript

## Phase 4: Test Method Coverage (Batch 4)
1. Add tests for uncovered public methods in TaskScheduler
2. Fix dynamic static method calls in tests

## Progress Tracking:
- Total Issues: 438 PHPStan errors + 1 test fatal error
- Batch 1: Test fixes (highest priority - blocks everything else)
- Batch 2: Core service functionality issues
- Batch 3: Remaining static analysis issues
- Batch 4: Test coverage improvements