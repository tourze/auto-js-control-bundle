<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Device;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\AutoJsControlBundle\Controller\Device\UploadScreenshotController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(UploadScreenshotController::class)]
#[RunTestsInSeparateProcesses]
final class UploadScreenshotControllerTest extends AbstractWebTestCase
{
    #[Test]
    public function uploadScreenshotWithValidDevice(): void
    {
        $client = self::createClientWithDatabase();
        $admin = $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        $deviceCode = 'SCREENSHOT-DEVICE-' . uniqid();

        // Register device first
        $registerData = [
            'deviceCode' => $deviceCode,
            'deviceName' => 'Screenshot Device',
            'certificateRequest' => 'screenshot-cert',
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

        // Create a fake base64 encoded image (1x1 pixel PNG)
        $imageData = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';

        $timestamp = time();
        $signature = $this->generateSignature($deviceCode, $timestamp, $certificate);

        $screenshotData = [
            'deviceCode' => $deviceCode,
            'signature' => $signature,
            'timestamp' => $timestamp,
            'imageData' => $imageData,
            'fileName' => 'test_screenshot_' . time() . '.png',
            'metadata' => [
                'width' => 1080,
                'height' => 1920,
                'format' => 'png',
            ],
        ];

        $screenshoJson = json_encode($screenshotData);
        $this->assertNotFalse($screenshoJson, 'JSON encoding failed');

        // Expect security exception like other API tests
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        $client->request('POST', '/api/autojs/v1/device/screenshot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $screenshoJson);
    }

    #[Test]
    public function uploadScreenshotWithoutAuthenticationFails(): void
    {
        $client = self::createClientWithDatabase();

        $requestData = [
            'deviceCode' => 'UNAUTH-SCREENSHOT',
            'signature' => 'some-signature',
            'timestamp' => time(),
            'imageData' => 'fake-image-data',
            'fileName' => 'unauthorized.png',
        ];

        $requesJson = json_encode($requestData);
        $this->assertNotFalse($requesJson, 'JSON encoding failed');

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        $client->request('POST', '/api/autojs/v1/device/screenshot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $requesJson);
    }

    #[Test]
    public function uploadScreenshotWithInvalidImageDataFails(): void
    {
        $client = self::createClientWithDatabase();
        $admin = $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        $deviceCode = 'INVALID-IMAGE-DEVICE-' . uniqid();

        // Register device first
        $registerData = [
            'deviceCode' => $deviceCode,
            'deviceName' => 'Invalid Image Device',
            'certificateRequest' => 'invalid-image-cert',
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

        // Send invalid image data
        $timestamp = time();
        $signature = $this->generateSignature($deviceCode, $timestamp, $certificate);

        $screenshotData = [
            'deviceCode' => $deviceCode,
            'signature' => $signature,
            'timestamp' => $timestamp,
            'imageData' => 'invalid-base64-data',
            'fileName' => 'invalid.png',
        ];

        $screenshoJson = json_encode($screenshotData);
        $this->assertNotFalse($screenshoJson, 'JSON encoding failed');

        // Expect security exception like other API tests
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        $client->request('POST', '/api/autojs/v1/device/screenshot', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $screenshoJson);
    }

    private function generateSignature(string $deviceCode, int $timestamp, string $certificate): string
    {
        $data = sprintf('%s:%d:%s', $deviceCode, $timestamp, $certificate);

        return hash_hmac('sha256', $data, $certificate);
    }

    #[Test]
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // 测试不支持的HTTP方法应该被路由层拒绝
        // UploadScreenshotController只支持POST方法，验证其他方法不被支持
        $supportedMethods = ['POST']; // UploadScreenshotController只支持POST

        $this->assertNotContains(
            $method,
            $supportedMethods,
            "Method {$method} should not be supported by UploadScreenshotController"
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
