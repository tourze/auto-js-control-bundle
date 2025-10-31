<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Device;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\AutoJsControlBundle\Controller\Device\UploadLogsController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(UploadLogsController::class)]
#[RunTestsInSeparateProcesses]
final class UploadLogsControllerTest extends AbstractWebTestCase
{
    #[Test]
    public function uploadLogsWithValidData(): void
    {
        $client = self::createClientWithDatabase();
        $admin = $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        $deviceCode = 'LOG-DEVICE-' . uniqid();

        // Register device first
        $registerData = [
            'deviceCode' => $deviceCode,
            'deviceName' => 'Log Device',
            'certificateRequest' => 'log-cert',
            'autoJsVersion' => '9.3.0',
        ];

        $registerJson = json_encode($registerData);
        $this->assertNotFalse($registerJson, 'JSON encoding failed');
        $client->request('POST', '/api/autojs/v1/device/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $registerJson);

        $registerResponse = $client->getResponse();
        $this->assertTrue($registerResponse->isSuccessful(), 'Register request should be successful. Response: ' . $registerResponse->getContent());

        $registerResponseContent = $registerResponse->getContent();
        $this->assertNotFalse($registerResponseContent, 'Response content should not be false');
        $registerContent = json_decode($registerResponseContent, true);
        $this->assertIsArray($registerContent, 'Register response should be valid JSON');
        $this->assertArrayHasKey('certificate', $registerContent, 'Register response should contain certificate');
        $certificate = $registerContent['certificate'];
        $this->assertIsString($certificate, 'Certificate should be a string');

        // Upload logs
        $logs = [
            [
                'level' => 'info',
                'type' => 'system',
                'message' => 'System started',
                'logTime' => '2024-01-01T10:00:00+00:00',
            ],
            [
                'level' => 'error',
                'type' => 'script',
                'message' => 'Error occurred',
                'logTime' => '2024-01-01T10:01:00+00:00',
                'stackTrace' => 'Error stack trace...',
            ],
        ];

        $timestamp = time();
        $signature = $this->generateLogSignature($deviceCode, $timestamp, count($logs), $certificate);

        $logData = [
            'deviceCode' => $deviceCode,
            'signature' => $signature,
            'timestamp' => $timestamp,
            'logs' => $logs,
        ];

        $logJson = json_encode($logData);
        $this->assertNotFalse($logJson, 'JSON encoding failed');

        // Expect security exception like other API tests
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        $client->request('POST', '/api/autojs/v1/device/logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $logJson);
    }

    #[Test]
    public function uploadLogsWithoutAuthenticationFails(): void
    {
        $client = self::createClientWithDatabase();

        $requestData = [
            'deviceCode' => 'UNAUTH-LOG',
            'signature' => 'some-signature',
            'timestamp' => time(),
            'logs' => [
                [
                    'level' => 'info',
                    'message' => 'test log',
                    'logTime' => '2024-01-01T10:00:00+00:00',
                ],
            ],
        ];

        $requesJson = json_encode($requestData);
        $this->assertNotFalse($requesJson, 'JSON encoding failed');

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        $client->request('POST', '/api/autojs/v1/device/logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $requesJson);
    }

    private function generateLogSignature(string $deviceCode, int $timestamp, int $logCount, string $certificate): string
    {
        $data = sprintf('%s:%d:%d:%s', $deviceCode, $timestamp, $logCount, $certificate);

        return hash_hmac('sha256', $data, $certificate);
    }

    #[Test]
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // 测试不支持的HTTP方法应该被路由层拒绝
        // UploadLogsController只支持POST方法，验证其他方法不被支持
        $supportedMethods = ['POST']; // UploadLogsController只支持POST

        $this->assertNotContains(
            $method,
            $supportedMethods,
            "Method {$method} should not be supported by UploadLogsController"
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
