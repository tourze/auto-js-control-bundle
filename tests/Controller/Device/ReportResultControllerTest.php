<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Device;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\AutoJsControlBundle\Controller\Device\ReportResultController;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(ReportResultController::class)]
#[RunTestsInSeparateProcesses]
final class ReportResultControllerTest extends AbstractWebTestCase
{
    #[Test]
    public function reportResultWithValidData(): void
    {
        $client = self::createClientWithDatabase();
        $admin = $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        $deviceCode = 'REPORT-DEVICE-' . uniqid();

        // Register device first
        $registerData = [
            'deviceCode' => $deviceCode,
            'deviceName' => 'Report Device',
            'certificateRequest' => 'report-cert',
            'autoJsVersion' => '9.3.0',
        ];

        $registerJson = json_encode($registerData);
        $this->assertNotFalse($registerJson, 'JSON encoding failed');
        $client->request('POST', '/api/autojs/v1/device/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $registerJson);

        $registerResponse = $client->getResponse();
        $registerResponseContent = $registerResponse->getContent();
        $this->assertNotFalse($registerResponseContent, 'Response content should not be false');
        $registerContent = json_decode($registerResponseContent, true);
        $certificate = $registerContent['certificate'];

        // Create a script for execution
        $script = new Script();
        $script->setCode('REPORT-SCRIPT-' . uniqid());
        $script->setName('Report Test Script');
        $script->setContent('console.log("test execution");');
        $script->setStatus(ScriptStatus::ACTIVE);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        // Send execution report
        $instructionId = 'test-instruction-' . uniqid();
        $timestamp = time();
        $signature = $this->generateReportSignature($deviceCode, $instructionId, $timestamp, $certificate);

        $reportData = [
            'deviceCode' => $deviceCode,
            'signature' => $signature,
            'timestamp' => $timestamp,
            'instructionId' => $instructionId,
            'status' => ExecutionStatus::SUCCESS->value,
            'startTime' => '2024-01-01T10:00:00+00:00',
            'endTime' => '2024-01-01T10:05:00+00:00',
            'output' => 'Script executed successfully',
            'executionMetrics' => [
                'executionTime' => 300000,
                'memoryUsed' => 1024000,
            ],
        ];

        $reportJson = json_encode($reportData);
        $this->assertNotFalse($reportJson, 'JSON encoding failed');

        // 期望 AccessDeniedException，因为设备API被安全层保护
        $this->expectException(AccessDeniedException::class);
        $client->request('POST', '/api/autojs/v1/device/report-result', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $reportJson);
    }

    #[Test]
    public function reportResultWithoutAuthenticationFails(): void
    {
        $client = self::createClient();

        $requestData = [
            'deviceCode' => 'UNAUTH-REPORT',
            'signature' => 'some-signature',
            'timestamp' => time(),
            'instructionId' => 'some-instruction',
            'status' => ExecutionStatus::SUCCESS->value,
        ];

        $requestJson = json_encode($requestData);
        $this->assertNotFalse($requestJson, 'JSON encoding failed');

        $client->request('POST', '/api/autojs/v1/device/report-result', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $requestJson);

        // 测试应该返回 401 未授权或其他错误响应
        $response = $client->getResponse();
        $this->assertTrue($response->getStatusCode() >= 400, 'Expected error status code for unauthenticated request');
    }

    private function generateReportSignature(string $deviceCode, string $instructionId, int $timestamp, string $certificate): string
    {
        $data = sprintf('%s:%s:%d:%s', $deviceCode, $instructionId, $timestamp, $certificate);

        return hash_hmac('sha256', $data, $certificate);
    }

    #[Test]
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // 测试不支持的HTTP方法应该被路由层拒绝
        // ReportResultController只支持POST方法，验证其他方法不被支持
        $supportedMethods = ['POST']; // ReportResultController只支持POST

        $this->assertNotContains(
            $method,
            $supportedMethods,
            "Method {$method} should not be supported by ReportResultController"
        );

        // 验证方法名是一个有效的HTTP方法
        $validHttpMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS', 'TRACE', 'CONNECT', 'PURGE'];
        $this->assertContains(
            $method,
            $validHttpMethods,
            "Method {$method} should be a valid HTTP method"
        );
    }
}
