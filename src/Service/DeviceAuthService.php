<?php

namespace Tourze\AutoJsControlBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Exception\DeviceAuthException;
use Tourze\AutoJsControlBundle\Exception\InvalidTaskArgumentException;

/**
 * 设备认证服务
 *
 * 负责设备的认证、签名验证和证书管理
 */
#[Autoconfigure(public: true)]
class DeviceAuthService
{
    private string $secretKey;

    public function __construct(
        private LoggerInterface $logger,
    ) {
        $this->secretKey = $_ENV['AUTO_JS_CONTROL_SECRET_KEY'] ?? 'default_secret_key';
    }

    /**
     * 生成设备证书.
     *
     * @param string $deviceCode         设备代码
     * @param string $certificateRequest 证书请求数据
     *
     * @return string 生成的证书
     */
    public function generateDeviceCertificate(string $deviceCode, string $certificateRequest): string
    {
        try {
            // 生成设备唯一密钥
            $deviceSecret = $this->generateDeviceSecret($deviceCode);

            // 组合证书数据
            $certificateData = sprintf(
                '%s:%s:%s:%d',
                $deviceCode,
                $certificateRequest,
                $deviceSecret,
                time()
            );

            // 生成证书签名
            $certificate = hash_hmac('sha256', $certificateData, $this->secretKey);

            $this->logger->info('设备证书生成成功', [
                'deviceCode' => $deviceCode,
                'certificateLength' => strlen($certificate),
            ]);

            return $certificate;
        } catch (\Exception $e) {
            $this->logger->error('生成设备证书失败', [
                'deviceCode' => $deviceCode,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw DeviceAuthException::certificateGenerationFailed($deviceCode, $e->getMessage());
        }
    }

    /**
     * 验证设备签名.
     *
     * @param string               $deviceCode     设备代码
     * @param string               $signature      待验证的签名
     * @param int                  $timestamp      时间戳
     * @param string               $certificate    设备证书
     * @param array<string, mixed> $additionalData 额外的签名数据
     */
    public function verifyDeviceSignature(
        string $deviceCode,
        string $signature,
        int $timestamp,
        string $certificate,
        array $additionalData = [],
    ): void {
        // 检查时间戳有效性（5分钟内）
        if (abs(time() - $timestamp) > 300) {
            throw BusinessLogicException::authenticationError('Timestamp expired');
        }

        // 构建签名数据
        $signatureData = $this->buildSignatureData($deviceCode, $timestamp, $certificate, $additionalData);

        // 计算预期签名
        $expectedSignature = hash_hmac('sha256', $signatureData, $certificate);

        // 验证签名
        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->warning('设备签名验证失败', [
                'deviceCode' => $deviceCode,
                'timestamp' => $timestamp,
                'providedSignature' => substr($signature, 0, 10) . '...',
                'expectedSignature' => substr($expectedSignature, 0, 10) . '...',
            ]);

            throw BusinessLogicException::authenticationError('Invalid signature');
        }

        $this->logger->debug('设备签名验证成功', [
            'deviceCode' => $deviceCode,
        ]);
    }

    /**
     * 生成API签名.
     *
     * @param string               $deviceCode  设备代码
     * @param string               $certificate 设备证书
     * @param array<string, mixed> $data        要签名的数据
     *
     * @return array<string, mixed> 包含签名和时间戳的数组
     */
    public function generateApiSignature(string $deviceCode, string $certificate, array $data = []): array
    {
        $timestamp = time();
        $signatureData = $this->buildSignatureData($deviceCode, $timestamp, $certificate, $data);
        $signature = hash_hmac('sha256', $signatureData, $certificate);

        return [
            'signature' => $signature,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * 生成设备密钥.
     */
    private function generateDeviceSecret(string $deviceCode): string
    {
        return hash_hmac('sha256', $deviceCode . ':device:secret', $this->secretKey);
    }

    /**
     * 构建签名数据.
     *
     * @param array<string, mixed> $additionalData
     */
    private function buildSignatureData(
        string $deviceCode,
        int $timestamp,
        string $certificate,
        array $additionalData = [],
    ): string {
        $parts = [
            $deviceCode,
            $timestamp,
            $certificate,
        ];

        // 添加额外数据
        if ([] !== $additionalData) {
            // 对数据进行排序以确保一致性
            ksort($additionalData);
            foreach ($additionalData as $key => $value) {
                if (is_array($value)) {
                    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
                    if (false === $encoded) {
                        throw new InvalidTaskArgumentException('JSON encoding of signature data failed');
                    }
                    $value = $encoded;
                }
                $parts[] = $key . '=' . $value;
            }
        }

        return implode(':', $parts);
    }

    /**
     * 刷新设备证书.
     *
     * @param string $deviceCode     设备代码
     * @param string $oldCertificate 旧证书
     *
     * @return string 新证书
     */
    public function refreshDeviceCertificate(string $deviceCode, string $oldCertificate): string
    {
        // 验证旧证书的有效性
        try {
            $this->verifyDeviceSignature($deviceCode, $oldCertificate, time(), $oldCertificate);
        } catch (BusinessLogicException $e) {
            throw BusinessLogicException::authenticationError('Invalid old certificate');
        }

        // 生成新证书
        $newCertificateRequest = sprintf('refresh:%s:%d', $oldCertificate, time());

        return $this->generateDeviceCertificate($deviceCode, $newCertificateRequest);
    }
}
