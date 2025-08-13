<?php

declare(strict_types=1);

use Speto\ApiPlatformInvokerBundle\Common\OperationValueResolver;
use Speto\ApiPlatformInvokerBundle\Processor\{ActionInvoker, DataValueResolver, InvokableProcessorDecorator};
use Speto\ApiPlatformInvokerBundle\Provider\{InvokableProviderDecorator, ProviderInvoker};
use Speto\ApiPlatformInvokerBundle\UriVar\{UriVarInstantiator, UriVarValueResolver};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $c): void {
    $s = $c->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $s->set(UriVarInstantiator::class);
    $s->set(UriVarValueResolver::class)
        ->tag('controller.argument_value_resolver', [
            'priority' => 150,
        ]);

    $s->set(DataValueResolver::class)
        ->tag('controller.argument_value_resolver', [
            'priority' => 200,
        ]);

    $s->set(OperationValueResolver::class)
        ->tag('controller.argument_value_resolver', [
            'priority' => 190,
        ]);

    $s->set(ActionInvoker::class);

    $s->set(InvokableProcessorDecorator::class)
        ->decorate('api_platform.state_processor')
        ->arg(0, service(InvokableProcessorDecorator::class . '.inner'));

    $s->set(ProviderInvoker::class);

    $s->set(InvokableProviderDecorator::class)
        ->decorate('api_platform.state_provider')
        ->arg(0, service(InvokableProviderDecorator::class . '.inner'));
};
