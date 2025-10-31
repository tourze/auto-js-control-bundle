<?php

namespace Tourze\AutoJsControlBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Exception\InvalidArgumentException;
use Tourze\AutoJsControlBundle\Repository\ScriptRepository;

/**
 * 脚本CRUD服务
 *
 * 负责脚本的创建、更新、删除等操作
 */
readonly class ScriptCrudService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ScriptRepository $scriptRepository,
        private ScriptDataValidationService $validationService,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * 创建脚本.
     *
     * @param array<string, mixed> $data
     */
    public function createScript(array $data): Script
    {
        $this->validationService->validateCreateData($data);

        $existing = $this->scriptRepository->findOneBy(['code' => $data['code']]);
        if (null !== $existing) {
            throw BusinessLogicException::invalidRequest('脚本编码已存在');
        }

        $scriptType = $this->validationService->parseScriptType($this->safelyParseString($data['scriptType'] ?? ''));
        $script = $this->buildScript($data, $scriptType);

        $this->validationService->validateScriptContent($script, $data);
        $this->setScriptContent($script, $data);
        $this->setOptionalFields($script, $data);

        $this->validateAndSave($script);

        return $script;
    }

    /**
     * 更新脚本.
     *
     * @param array<string, mixed> $data
     */
    public function updateScript(int $scriptId, array $data): Script
    {
        $script = $this->getScriptById($scriptId);

        $this->updateBasicInfo($script, $data);
        $this->updateScriptContentByType($script, $data);
        $this->setOptionalFields($script, $data);

        $this->validateAndSave($script);

        return $script;
    }

    /**
     * 删除脚本（软删除）.
     */
    public function deleteScript(int $scriptId): void
    {
        $script = $this->getScriptById($scriptId);

        if (!$script->getTasks()->isEmpty()) {
            throw BusinessLogicException::invalidRequest('脚本正在被任务使用，无法删除');
        }

        $script->setValid(false);
        $this->entityManager->flush();
    }

    /**
     * 切换脚本启用状态
     *
     * @return array<string, mixed>
     */
    public function toggleScriptStatus(int $scriptId): array
    {
        $script = $this->getScriptById($scriptId);
        $script->setValid(!$script->isValid());
        $this->entityManager->flush();

        return [
            'valid' => $script->isValid(),
            'message' => $script->isValid() ? '脚本已启用' : '脚本已禁用',
        ];
    }

    /**
     * 复制脚本.
     *
     * @param array<string, mixed> $data
     */
    public function duplicateScript(int $sourceScriptId, array $data): Script
    {
        if (!isset($data['code']) || '' === $data['code']) {
            throw BusinessLogicException::invalidRequest('必须提供新的脚本编码');
        }

        $existing = $this->scriptRepository->findOneBy(['code' => $data['code']]);
        if (null !== $existing) {
            throw BusinessLogicException::invalidRequest('脚本编码已存在');
        }

        $sourceScript = $this->getScriptById($sourceScriptId);

        $newScript = new Script();
        $newScript->setCode($this->safelyParseString($data['code']));
        $newScript->setName($this->safelyParseString($data['name'] ?? $sourceScript->getName() . ' (复制)'));
        $newScript->setDescription($sourceScript->getDescription());
        $newScript->setScriptType($sourceScript->getScriptType());
        $newScript->setContent($sourceScript->getContent());
        $newScript->setProjectPath($sourceScript->getProjectPath());
        $newScript->setVersion(1);
        $newScript->setParameters($sourceScript->getParameters());
        $newScript->setPriority($sourceScript->getPriority());
        $newScript->setTimeout($sourceScript->getTimeout());
        $newScript->setMaxRetries($sourceScript->getMaxRetries());
        $newScript->setSecurityRules($sourceScript->getSecurityRules());
        $newScript->setValid(true);

        $this->entityManager->persist($newScript);
        $this->entityManager->flush();

        return $newScript;
    }

    private function getScriptById(int $scriptId): Script
    {
        $script = $this->scriptRepository->find($scriptId);
        if (null === $script) {
            throw BusinessLogicException::resourceStateError('脚本不存在');
        }

        return $script;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildScript(array $data, ScriptType $scriptType): Script
    {
        $script = new Script();
        $script->setCode($this->safelyParseString($data['code']));
        $script->setName($this->safelyParseString($data['name']));
        $script->setDescription($this->safelyParseNullableString($data['description'] ?? null));
        $script->setScriptType($scriptType);
        $script->setVersion($this->safelyParseInt($data['version'] ?? 1));
        $script->setPriority($this->safelyParseInt($data['priority'] ?? 0));
        $script->setTimeout($this->safelyParseInt($data['timeout'] ?? 3600));
        $script->setMaxRetries($this->safelyParseInt($data['maxRetries'] ?? 3));

        return $script;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setScriptContent(Script $script, array $data): void
    {
        if (ScriptType::JAVASCRIPT === $script->getScriptType()) {
            $script->setContent($this->safelyParseNullableString($data['content'] ?? null));
        } elseif (ScriptType::AUTO_JS === $script->getScriptType()) {
            $script->setProjectPath($this->safelyParseNullableString($data['projectPath'] ?? null));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setOptionalFields(Script $script, array $data): void
    {
        if (isset($data['parameters'])) {
            $parametersArray = $this->safelyParseArray($data['parameters']);
            $this->validationService->validateParametersDefinition($parametersArray);
            $encoded = json_encode($data['parameters']);
            if (false === $encoded) {
                throw new InvalidArgumentException('JSON encoding of parameters failed');
            }
            $script->setParameters($encoded);
        }

        if (isset($data['securityRules'])) {
            $encoded = json_encode($data['securityRules']);
            if (false === $encoded) {
                throw new InvalidArgumentException('JSON encoding of security rules failed');
            }
            $script->setSecurityRules($encoded);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateBasicInfo(Script $script, array $data): void
    {
        if (isset($data['name'])) {
            $script->setName($this->safelyParseString($data['name']));
        }
        if (isset($data['description'])) {
            $script->setDescription($this->safelyParseNullableString($data['description']));
        }
        if (isset($data['priority'])) {
            $script->setPriority($this->safelyParseInt($data['priority']));
        }
        if (isset($data['timeout'])) {
            $script->setTimeout($this->safelyParseInt($data['timeout']));
        }
        if (isset($data['maxRetries'])) {
            $script->setMaxRetries($this->safelyParseInt($data['maxRetries']));
        }
        if (isset($data['version'])) {
            $script->setVersion($this->safelyParseInt($data['version']));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateScriptContentByType(Script $script, array $data): void
    {
        if (ScriptType::JAVASCRIPT === $script->getScriptType() && isset($data['content'])) {
            $script->setContent($this->safelyParseNullableString($data['content']));
        } elseif (ScriptType::AUTO_JS === $script->getScriptType() && isset($data['projectPath'])) {
            $script->setProjectPath($this->safelyParseNullableString($data['projectPath']));
        }
    }

    private function validateAndSave(Script $script): void
    {
        $violations = $this->validator->validate($script);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            $errorJson = json_encode($errors);
            if (false === $errorJson) {
                throw BusinessLogicException::validationFailed('验证失败且无法编码错误信息');
            }
            throw BusinessLogicException::validationFailed('验证失败: ' . $errorJson);
        }

        if (null === $script->getId()) {
            $this->entityManager->persist($script);
        }
        $this->entityManager->flush();
    }

    /**
     * 安全地将混合类型转换为字符串
     */
    private function safelyParseString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * 安全地将混合类型转换为可空字符串
     */
    private function safelyParseNullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * 安全地将混合类型转换为整数
     */
    private function safelyParseInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * 安全地将混合类型转换为数组
     *
     * @return array<string, mixed>
     */
    private function safelyParseArray(mixed $value): array
    {
        if (is_array($value)) {
            // 确保数组键为字符串类型
            $result = [];
            foreach ($value as $key => $val) {
                $stringKey = is_string($key) ? $key : (string) $key;
                $result[$stringKey] = $val;
            }

            return $result;
        }

        return [];
    }
}
