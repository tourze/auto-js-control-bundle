<?php

namespace Tourze\AutoJsControlBundle\Controller;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * 验证器感知特质.
 *
 * 为需要验证功能的控制器提供验证器注入
 */
trait ValidatorAwareTrait
{
    protected ValidatorInterface $validator;

    /**
     * 设置验证器.
     */
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * 验证请求数据.
     *
     * @throws BadRequestHttpException
     */
    protected function validateRequest(object $dto): void
    {
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            throw new BadRequestHttpException('Validation failed', null, 0, ['validation_errors' => $errors]);
        }
    }
}
