<?php

declare(strict_types=1);

namespace EnvForm\ServiceDetection;

use EnvForm\ServiceDetection\DTO\ServiceContext;

final class Service implements ServiceDetectionInterface
{
    private ?ServiceContext $context = null;

    final public function __construct(
        private readonly Repository $repository,
        private readonly \EnvForm\ValueResolver\ValueResolverInterface $valueResolver
    ) {}

    final public function refresh(): void
    {
        $this->context = null;
    }

    private function getContext(): ServiceContext
    {
        if ($this->context === null) {
            $this->context = $this->resolveContext();
        }

        return $this->context;
    }

    private function resolveContext(): ServiceContext
    {
        $map = $this->repository->getMap();
        $activeServices = [];
        $keyToServiceMap = [];

        foreach ($map as $name => $definition) {
            // Track key patterns for optimized lookup
            foreach ($definition->patterns as $pattern) {
                $keyToServiceMap[$pattern] = $name;
            }

            $isActive = false;

            // 1. Check Activators (Drivers)
            foreach ($definition->activators as $configKey => $expectedValues) {
                $actualValue = $this->valueResolver->resolve($configKey);
                if (\in_array((string) $actualValue, $expectedValues, true)) {
                    $isActive = true;
                    break;
                }
            }

            // 2. Check Master Keys (Implicit Activation)
            if (! $isActive) {
                foreach ($definition->masterKeys as $configKey) {
                    if ($this->valueResolver->has($configKey)) {
                        $isActive = true;
                        break;
                    }
                }
            }

            if ($isActive) {
                $activeServices[] = $name;
            }
        }

        return new ServiceContext($activeServices, $keyToServiceMap);
    }

    final public function isActive(string $serviceName): bool
    {
        return \in_array($serviceName, $this->getContext()->activeServices, true);
    }

    final public function isKeyRelevant(string $configKey): bool
    {
        $context = $this->getContext();
        $serviceName = null;

        // Find which service this key belongs to
        foreach ($context->keyToServiceMap as $pattern => $name) {
            if (fnmatch($pattern, $configKey)) {
                $serviceName = $name;
                break;
            }
        }

        // If not mapped to any service, it's always relevant
        if ($serviceName === null) {
            return true;
        }

        $isActive = $this->isActive($serviceName);

        return $isActive;
    }
}
