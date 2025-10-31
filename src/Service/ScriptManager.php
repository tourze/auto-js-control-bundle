<?php

namespace Tourze\AutoJsControlBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Exception\InvalidArgumentException;
use Tourze\AutoJsControlBundle\Repository\ScriptRepository;

/**
 * 脚本管理服务
 *
 * 负责脚本的CRUD操作、版本管理和验证
 */
readonly class ScriptManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ScriptRepository $scriptRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 创建脚本.
     *
     * @param array<string, mixed> $data 脚本数据
     *
     * @return Script 脚本实体
     */
    public function createScript(array $data): Script
    {
        try {
            $script = new Script();
            $this->updateScriptFromData($script, $data);

            // 设置初始版本
            $script->setVersion(1);

            // 计算内容校验和
            if (isset($data['content']) && is_string($data['content'])) {
                $script->setChecksum($this->calculateChecksum($data['content']));
            }

            $this->entityManager->persist($script);
            $this->entityManager->flush();

            $this->logger->info('脚本创建成功', [
                'scriptId' => $script->getId(),
                'scriptCode' => $script->getCode(),
                'scriptName' => $script->getName(),
            ]);

            return $script;
        } catch (\Exception $e) {
            $this->logger->error('创建脚本失败', [
                'error' => $e->getMessage(),
                'data' => $data,
                'exception' => $e,
            ]);

            throw BusinessLogicException::configurationError('创建脚本失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新脚本.
     *
     * @param int                  $scriptId 脚本ID
     * @param array<string, mixed> $data     更新数据
     *
     * @return Script 更新后的脚本
     */
    public function updateScript(int $scriptId, array $data): Script
    {
        try {
            $script = $this->getScript($scriptId);

            // 检查内容是否变化，如果变化则增加版本号
            if (isset($data['content']) && is_string($data['content']) && $data['content'] !== $script->getContent()) {
                $script->setVersion($script->getVersion() + 1);
                $script->setChecksum($this->calculateChecksum($data['content']));
            }

            $this->updateScriptFromData($script, $data);

            $this->entityManager->flush();

            $this->logger->info('脚本更新成功', [
                'scriptId' => $script->getId(),
                'scriptCode' => $script->getCode(),
                'version' => $script->getVersion(),
            ]);

            return $script;
        } catch (\Exception $e) {
            $this->logger->error('更新脚本失败', [
                'scriptId' => $scriptId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw BusinessLogicException::configurationError('更新脚本失败: ' . $e->getMessage());
        }
    }

    /**
     * 从数据更新脚本实体.
     *
     * @param array<string, mixed> $data
     */
    private function updateScriptFromData(Script $script, array $data): void
    {
        $this->updateBasicFields($script, $data);
        $this->updateSpecialFields($script, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateBasicFields(Script $script, array $data): void
    {
        $this->updateCode($script, $data);
        $this->updateName($script, $data);
        $this->updateDescription($script, $data);
        $this->updateScriptType($script, $data);
        $this->updateContent($script, $data);
        $this->updateTags($script, $data);
    }

    /** @param array<string, mixed> $data */
    private function updateCode(Script $script, array $data): void
    {
        if (!isset($data['code']) || !is_string($data['code'])) {
            return;
        }
        $script->setCode($data['code']);
    }

    /** @param array<string, mixed> $data */
    private function updateName(Script $script, array $data): void
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            return;
        }
        $script->setName($data['name']);
    }

    /** @param array<string, mixed> $data */
    private function updateDescription(Script $script, array $data): void
    {
        if (!array_key_exists('description', $data)) {
            return;
        }
        $description = $data['description'];
        if (!is_string($description) && null !== $description) {
            return;
        }
        $script->setDescription($description);
    }

    /** @param array<string, mixed> $data */
    private function updateScriptType(Script $script, array $data): void
    {
        if (!isset($data['scriptType']) || !$data['scriptType'] instanceof ScriptType) {
            return;
        }
        $script->setScriptType($data['scriptType']);
    }

    /** @param array<string, mixed> $data */
    private function updateContent(Script $script, array $data): void
    {
        if (!array_key_exists('content', $data)) {
            return;
        }
        $content = $data['content'];
        if (!is_string($content) && null !== $content) {
            return;
        }
        $script->setContent($content);
    }

    /** @param array<string, mixed> $data */
    private function updateTags(Script $script, array $data): void
    {
        if (!isset($data['tags']) || !is_array($data['tags'])) {
            return;
        }
        /** @var array<string> $tags */
        $tags = array_values(array_filter($data['tags'], 'is_string'));
        $script->setTags($tags);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateSpecialFields(Script $script, array $data): void
    {
        $this->updateParameters($script, $data);
        $this->updateTimeout($script, $data);
        $this->updateMaxRetries($script, $data);
    }

    /** @param array<string, mixed> $data */
    private function updateParameters(Script $script, array $data): void
    {
        if (!isset($data['parameters'])) {
            return;
        }

        if (is_string($data['parameters'])) {
            $parameters = $data['parameters'];
        } else {
            $encoded = json_encode($data['parameters']);
            if (false === $encoded) {
                throw new InvalidArgumentException('JSON encoding of parameters failed');
            }
            $parameters = $encoded;
        }
        $script->setParameters($parameters);
    }

    /** @param array<string, mixed> $data */
    private function updateTimeout(Script $script, array $data): void
    {
        if (isset($data['timeout']) && (is_int($data['timeout']) || is_numeric($data['timeout']))) {
            $script->setTimeout((int) $data['timeout']);
        }
    }

    /** @param array<string, mixed> $data */
    private function updateMaxRetries(Script $script, array $data): void
    {
        if (isset($data['maxRetries']) && (is_int($data['maxRetries']) || is_numeric($data['maxRetries']))) {
            $script->setMaxRetries((int) $data['maxRetries']);
        }
    }

    /**
     * 获取脚本.
     *
     * @param int $scriptId 脚本ID
     *
     * @return Script 脚本实体
     */
    public function getScript(int $scriptId): Script
    {
        $script = $this->scriptRepository->find($scriptId);
        if (null === $script) {
            throw BusinessLogicException::scriptNotFound($scriptId);
        }

        return $script;
    }

    /**
     * 获取脚本（通过代码）.
     *
     * @param string $scriptCode 脚本代码
     *
     * @return Script 脚本实体
     */
    public function getScriptByCode(string $scriptCode): Script
    {
        $script = $this->scriptRepository->findOneBy(['code' => $scriptCode]);
        if (null === $script) {
            throw BusinessLogicException::scriptNotFound(0);
        }

        return $script;
    }

    /**
     * 删除脚本（软删除）.
     *
     * @param int $scriptId 脚本ID
     */
    public function deleteScript(int $scriptId): void
    {
        try {
            $script = $this->getScript($scriptId);
            $script->setValid(false);

            $this->entityManager->flush();

            $this->logger->info('脚本已删除', [
                'scriptId' => $scriptId,
                'scriptCode' => $script->getCode(),
            ]);
        } catch (BusinessLogicException $e) {
            $this->logger->warning('尝试删除不存在的脚本', [
                'scriptId' => $scriptId,
            ]);

            throw $e;
        }
    }

    /**
     * 更新脚本状态
     *
     * @param int    $scriptId 脚本ID
     * @param string $status   新状态
     */
    public function updateScriptStatus(int $scriptId, string $status): void
    {
        $script = $this->getScript($scriptId);
        $oldStatus = $script->getStatus();
        $script->setStatus(ScriptStatus::from($status));

        $this->entityManager->flush();

        $this->logger->info('脚本状态已更新', [
            'scriptId' => $scriptId,
            'oldStatus' => $oldStatus,
            'newStatus' => $status,
        ]);
    }

    /**
     * 验证脚本内容.
     *
     * @param string $content    脚本内容
     * @param string $scriptType 脚本类型
     *
     * @return array<string, mixed> 验证结果 ['valid' => bool, 'errors' => array<string>]
     */
    public function validateScriptContent(string $content, string $scriptType): array
    {
        $errors = [];

        // 基本验证
        if ('' === trim($content)) {
            $errors[] = '脚本内容不能为空';
        }

        // 根据脚本类型进行特定验证
        switch ($scriptType) {
            case 'javascript':
                $errors = array_merge($errors, $this->validateJavaScript($content));
                break;

            case 'auto_js':
                $errors = array_merge($errors, $this->validateAutoJs($content));
                break;

            case 'shell':
                $errors = array_merge($errors, $this->validateShellScript($content));
                break;
        }

        return [
            'valid' => [] === $errors,
            'errors' => $errors,
        ];
    }

    /**
     * 验证JavaScript脚本.
     *
     * @return array<string>
     */
    private function validateJavaScript(string $content): array
    {
        $errors = [];

        // 检查危险函数
        $dangerousFunctions = ['eval', 'Function', 'setTimeout', 'setInterval'];
        foreach ($dangerousFunctions as $func) {
            if (false !== stripos($content, $func)) {
                $errors[] = "脚本包含潜在危险函数: {$func}";
            }
        }

        // 检查基本语法（简单检查）
        $openBraces = substr_count($content, '{');
        $closeBraces = substr_count($content, '}');
        if ($openBraces !== $closeBraces) {
            $errors[] = '大括号不匹配';
        }

        return $errors;
    }

    /**
     * 验证Auto.js脚本.
     *
     * @return array<string>
     */
    private function validateAutoJs(string $content): array
    {
        $errors = [];

        // Auto.js特定的验证
        // 检查是否包含必要的Auto.js API调用
        $autoJsKeywords = ['auto', 'app', 'device', 'toast'];
        $hasAutoJsApi = false;

        foreach ($autoJsKeywords as $keyword) {
            if (false !== stripos($content, $keyword)) {
                $hasAutoJsApi = true;
                break;
            }
        }

        if (!$hasAutoJsApi) {
            $errors[] = '未检测到Auto.js API调用';
        }

        return $errors;
    }

    /**
     * 验证Shell脚本.
     *
     * @return array<string>
     */
    private function validateShellScript(string $content): array
    {
        $errors = [];

        // 检查危险命令
        $dangerousCommands = ['rm -rf', 'mkfs', 'dd if=', ':(){ :|:& };:'];
        foreach ($dangerousCommands as $cmd) {
            if (false !== stripos($content, $cmd)) {
                $errors[] = "脚本包含危险命令: {$cmd}";
            }
        }

        return $errors;
    }

    /**
     * 计算内容校验和.
     */
    private function calculateChecksum(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * 搜索脚本.
     *
     * @param array<string, mixed>  $criteria 搜索条件
     * @param array<string, 'ASC'|'asc'|'DESC'|'desc'> $orderBy  排序条件
     * @param int                   $limit    限制数量
     * @param int                   $offset   偏移量
     *
     * @return array<Script> 脚本列表
     */
    public function searchScripts(
        array $criteria = [],
        array $orderBy = ['id' => 'DESC'],
        int $limit = 20,
        int $offset = 0,
    ): array {
        // 默认只查询有效的脚本
        if (!isset($criteria['valid'])) {
            $criteria['valid'] = true;
        }

        return $this->scriptRepository->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * 获取脚本统计信息.
     *
     * @return array<string, mixed> 统计信息
     */
    public function getScriptStatistics(): array
    {
        $stats = [
            'total' => 0,
            'byStatus' => [],
            'byType' => [],
            'recentlyUpdated' => [],
        ];

        // 总数统计
        $stats['total'] = $this->scriptRepository->count(['valid' => true]);

        // 按状态统计
        $statuses = ['draft', 'testing', 'active', 'inactive', 'deprecated'];
        foreach ($statuses as $status) {
            $stats['byStatus'][$status] = $this->scriptRepository->count([
                'status' => $status,
                'valid' => true,
            ]);
        }

        // 按类型统计
        $types = ['javascript', 'auto_js', 'shell'];
        foreach ($types as $type) {
            $stats['byType'][$type] = $this->scriptRepository->count([
                'scriptType' => $type,
                'valid' => true,
            ]);
        }

        // 最近更新的脚本
        $recentScripts = $this->scriptRepository->findBy(
            ['valid' => true],
            ['updatedAt' => 'DESC'],
            5
        );

        foreach ($recentScripts as $script) {
            $stats['recentlyUpdated'][] = [
                'id' => $script->getId(),
                'code' => $script->getCode(),
                'name' => $script->getName(),
                'updatedAt' => $script->getUpdateTime()?->format(\DateTimeInterface::RFC3339),
            ];
        }

        return $stats;
    }

    /**
     * 复制脚本.
     *
     * @param int    $scriptId 源脚本ID
     * @param string $newCode  新脚本代码
     * @param string $newName  新脚本名称
     *
     * @return Script 新脚本
     */
    public function duplicateScript(int $scriptId, string $newCode, string $newName): Script
    {
        $sourceScript = $this->getScript($scriptId);

        $newScript = new Script();
        $newScript->setCode($newCode);
        $newScript->setName($newName);

        $sourceDescription = $sourceScript->getDescription();
        $newDescription = (null !== $sourceDescription) ? $sourceDescription . ' (复制)' : '(复制)';
        $newScript->setDescription($newDescription);

        $newScript->setScriptType($sourceScript->getScriptType());
        $newScript->setContent($sourceScript->getContent());
        $newScript->setParameters($sourceScript->getParameters());
        $newScript->setTimeout($sourceScript->getTimeout());
        $newScript->setMaxRetries($sourceScript->getMaxRetries());
        $newScript->setTags($sourceScript->getTags());
        $newScript->setVersion(1);
        $newScript->setChecksum($sourceScript->getChecksum());
        $newScript->setStatus(ScriptStatus::DRAFT);

        $this->entityManager->persist($newScript);
        $this->entityManager->flush();

        $this->logger->info('脚本复制成功', [
            'sourceScriptId' => $scriptId,
            'newScriptId' => $newScript->getId(),
            'newScriptCode' => $newCode,
        ]);

        return $newScript;
    }
}
