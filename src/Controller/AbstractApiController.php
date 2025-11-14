<?php

namespace Tourze\AutoJsControlBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * API控制器基类.
 *
 * 提供通用的API响应方法和错误处理
 */
abstract class AbstractApiController extends AbstractController
{
    /**
     * 创建成功响应.
     */
    /**
     * @param array<string, mixed> $data
     */
    protected function successResponse(array $data = [], int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    /**
     * 创建错误响应.
     */
    /**
     * @param array<string, mixed>|null $errors
     */
    protected function errorResponse(string $message, int $status = Response::HTTP_BAD_REQUEST, ?array $errors = null): JsonResponse
    {
        $data = [
            'status' => 'error',
            'message' => $message,
            'serverTime' => new \DateTimeImmutable()->format('c'),
        ];

        if (null !== $errors) {
            $data['errors'] = $errors;
        }

        return new JsonResponse($data, $status);
    }

    /**
     * 获取请求中的JSON数据.
     *
     * @return array<string, mixed>
     */
    protected function getJsonData(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new \JsonException('Invalid JSON structure');
            }

            /** @var array<string, mixed> $data */
            return $data;
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Invalid JSON: ' . $e->getMessage());
        }
    }

    /**
     * 验证设备签名.
     *
     * @throws UnauthorizedHttpException
     */
    protected function verifyDeviceSignature(string $deviceCode, string $signature, int $timestamp, string $certificate): void
    {
        // 验证时间戳（5分钟时间窗口）
        $now = time();
        if (abs($now - $timestamp) > 300) {
            throw new UnauthorizedHttpException('Invalid timestamp', '时间戳无效，请同步系统时间');
        }

        // 验证签名
        $data = sprintf('%s:%d:%s', $deviceCode, $timestamp, $certificate);
        $expectedSignature = hash_hmac('sha256', $data, $certificate);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new UnauthorizedHttpException('Invalid signature', '签名验证失败');
        }
    }

    /**
     * 获取请求IP地址
     */
    protected function getRequestIp(Request $request): string
    {
        return $request->getClientIp() ?? '0.0.0.0';
    }

    /**
     * 处理文件上传.
     */
    protected function handleFileUpload(Request $request, string $fieldName): ?string
    {
        $uploadedFile = $request->files->get($fieldName);
        if (null === $uploadedFile) {
            return null;
        }

        if (!$uploadedFile instanceof UploadedFile) {
            throw new BadRequestHttpException('无效的文件上传对象');
        }

        if (!$uploadedFile->isValid()) {
            $error = $uploadedFile->getError();
            $errorMessage = \UPLOAD_ERR_OK !== $error ? $this->getUploadErrorMessage($error) : '未知错误';
            throw new BadRequestHttpException('文件上传失败: ' . $errorMessage);
        }

        // 生成唯一文件名
        $originalName = $uploadedFile->getClientOriginalName();
        if ('' === $originalName) {
            throw new BadRequestHttpException('无法获取文件原始名称');
        }
        $originalFilename = pathinfo($originalName, \PATHINFO_FILENAME);
        if (!is_string($originalFilename) || '' === $originalFilename) {
            throw new BadRequestHttpException('无效的文件名');
        }
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        $extension = $uploadedFile->guessExtension();
        if (null === $extension) {
            throw new BadRequestHttpException('无法确定文件扩展名');
        }
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

        // 移动文件到上传目录
        try {
            $projectDir = $this->getParameter('kernel.project_dir');
            if (!is_string($projectDir)) {
                throw new BadRequestHttpException('无效的项目目录配置');
            }
            $uploadedFile->move(
                $projectDir . '/var/uploads',
                $newFilename
            );

            return $newFilename;
        } catch (\Exception $e) {
            throw new BadRequestHttpException('文件保存失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取文件上传错误信息.
     */
    private function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            \UPLOAD_ERR_INI_SIZE => '文件大小超过php.ini限制',
            \UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            \UPLOAD_ERR_PARTIAL => '文件仅部分上传',
            \UPLOAD_ERR_NO_FILE => '没有文件上传',
            \UPLOAD_ERR_NO_TMP_DIR => '缺少临时目录',
            \UPLOAD_ERR_CANT_WRITE => '文件写入磁盘失败',
            \UPLOAD_ERR_EXTENSION => 'PHP扩展阻止了文件上传',
            default => '未知上传错误',
        };
    }
}
