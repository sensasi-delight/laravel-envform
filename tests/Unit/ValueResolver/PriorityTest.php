<?php

declare(strict_types=1);

namespace Tests\Unit\ValueResolver;

use EnvForm\DotEnv;
use EnvForm\FormValue;
use EnvForm\Registry;
use EnvForm\ValueResolver\Repository;
use EnvForm\ValueResolver\Service;
use PHPUnit\Framework\MockObject\MockObject;

final class PriorityTest extends ValueResolverTestCase
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

    final public function test_form_value_has_highest_priority(): void
    {
        $key = 'some.key';

        $this->formValue->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn('form_value');

        // Other sources shouldn't even be called if FormValue is found
        $this->dotEnv->expects($this->never())->method('getExistingValue');
        $this->registry->expects($this->never())->method('getStaticValue');
        $this->repository->expects($this->never())->method('find');

        $result = $this->service->resolve($key);

        $this->assertEquals('form_value', $result);
    }

    final public function test_dotenv_has_priority_over_config_and_implicit(): void
    {
        $key = 'some.key';

        $this->formValue->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn(null);

        $this->dotEnv->expects($this->once())
            ->method('getExistingValue')
            ->with($key)
            ->willReturn('dotenv_value');

        $this->registry->expects($this->never())->method('getStaticValue');
        $this->repository->expects($this->never())->method('find');

        $result = $this->service->resolve($key);

        $this->assertEquals('dotenv_value', $result);
    }

    final public function test_config_default_has_priority_over_implicit(): void
    {
        $key = 'some.key';

        $this->formValue->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn(null);

        $this->dotEnv->expects($this->once())
            ->method('getExistingValue')
            ->with($key)
            ->willReturn(null);

        $this->registry->expects($this->once())
            ->method('getStaticValue')
            ->with($key)
            ->willReturn('config_value');

        $this->repository->expects($this->never())->method('find');

        $result = $this->service->resolve($key);

        $this->assertEquals('config_value', $result);
    }
}
