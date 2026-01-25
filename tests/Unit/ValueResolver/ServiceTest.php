<?php

declare(strict_types=1);

namespace Tests\Unit\ValueResolver;

use EnvForm\DotEnv;
use EnvForm\FormValue;
use EnvForm\Registry;
use EnvForm\ValueResolver\Repository;
use EnvForm\ValueResolver\Service;
use PHPUnit\Framework\MockObject\MockObject;

final class ServiceTest extends ValueResolverTestCase
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

    final public function test_it_resolves_via_implicit_rule_when_other_sources_empty(): void
    {
        $key = 'some.key';
        $envVar = new \EnvForm\DTO\EnvVar(
            configKeys: collect([$key]),
            default: 'config_value',
            group: 'app.php',
            isTrigger: false,
            key: 'SOME_KEY'
        );

        $this->registry->expects($this->once())
            ->method('find')
            ->with($key)
            ->willReturn($envVar);

        $this->formValue->expects($this->once())
            ->method('get')
            ->with('SOME_KEY')
            ->willReturn(null);

        $this->dotEnv->expects($this->once())
            ->method('getExistingValue')
            ->with('SOME_KEY')
            ->willReturn(null);

        $this->registry->expects($this->once())
            ->method('getStaticValue')
            ->with($key)
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($key)
            ->willReturn(fn () => 'implicit_value');

        $result = $this->service->resolve($key);

        $this->assertEquals('implicit_value', $result);
    }
}
