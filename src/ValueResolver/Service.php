<?php

declare(strict_types=1);

namespace EnvForm\ValueResolver;

use EnvForm\DotEnv;
use EnvForm\FormValue;
use EnvForm\Registry;
use LogicException;

final class Service implements ValueResolverInterface
{
    /**
     * @var string[]
     */
    private array $resolutionStack = [];

    final public function __construct(
        private readonly DotEnv\Service $dotEnv,
        private readonly FormValue\Service $formValue,
        private readonly Registry\Service $registry,
        private readonly Repository $repository,
    ) {}

    /**
     * Resolves a value for a given config path or environment key.
     * Priority: FormValue > DotEnv > Config Default > Implicit
     *
     * @param  string  $key  Dot-notation config path or Env Key
     *
     * @throws LogicException On circular dependencies
     */
    final public function resolve(string $key): mixed
    {
        if (\in_array($key, $this->resolutionStack, true)) {
            throw new LogicException("Circular dependency detected for key: {$key}");
        }

        $this->resolutionStack[] = $key;

        try {
            // 1. FormValue
            $formValue = $this->formValue->get($key);
            if ($formValue !== null) {
                return $formValue;
            }

            // 2. DotEnv
            $dotEnvValue = $this->dotEnv->getExistingValue($key);
            if ($dotEnvValue !== null) {
                return $dotEnvValue;
            }

            // 3. Config Default (Registry)
            $configDefault = $this->registry->getStaticValue($key);
            if ($configDefault !== null) {
                return $configDefault;
            }

            // 4. Implicit
            $rule = $this->repository->find($key);
            if ($rule !== null) {
                return $rule($this);
            }

            return null;
        } finally {
            array_pop($this->resolutionStack);
        }
    }
}
