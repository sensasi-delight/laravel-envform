<?php

declare(strict_types=1);

namespace Tests\Unit\ValueResolver;

use EnvForm\DotEnv;
use EnvForm\FormValue;
use EnvForm\Registry;
use EnvForm\ValueResolver\Repository;
use EnvForm\ValueResolver\Service;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;

final class CircularDependencyTest extends ValueResolverTestCase
{
    private Service $service;

    /** @var DotEnv\Service&MockObject */
    private $dotEnv;

    /** @var FormValue\Service&MockObject */
    private $formValue;

    /** @var Registry\Service&MockObject */
    private $registry;

    /** @var Repository&MockObject */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var \PHPUnit\Framework\MockObject\MockObject&DotEnv\Service $dotEnv */
        $dotEnv = $this->createMock(DotEnv\Service::class);
        $this->dotEnv = $dotEnv;

        /** @var \PHPUnit\Framework\MockObject\MockObject&FormValue\Service $formValue */
        $formValue = $this->createMock(FormValue\Service::class);
        $this->formValue = $formValue;

        /** @var \PHPUnit\Framework\MockObject\MockObject&Registry\Service $registry */
        $registry = $this->createMock(Registry\Service::class);
        $this->registry = $registry;

        /** @var \PHPUnit\Framework\MockObject\MockObject&Repository $repository */
        $repository = $this->createMock(Repository::class);
        $this->repository = $repository;

        $this->service = new Service(
            $this->dotEnv,
            $this->formValue,
            $this->registry,
            $this->repository
        );
    }

    final public function test_it_throws_exception_on_circular_dependency(): void
    {
        $keyA = 'key.a';
        $keyB = 'key.b';

        $this->formValue->method('get')->willReturn(null);
        $this->dotEnv->method('getExistingValue')->willReturn(null);
        $this->registry->method('getStaticValue')->willReturn(null);

        $this->repository->method('find')->willReturnMap([
            [$keyA, fn ($resolver) => $resolver->resolve($keyB)],
            [$keyB, fn ($resolver) => $resolver->resolve($keyA)],
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Circular dependency detected for key: key.a');

        $this->service->resolve($keyA);
    }
}
