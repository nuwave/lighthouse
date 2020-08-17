<?php

use Rector\Core\Configuration\Option;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedParameterRector;
use Rector\Set\ValueObject\SetList;
use Rector\SOLID\Rector\ClassMethod\UseInterfaceOverImplementationInConstructorRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::SETS, [
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::PHPUNIT_EXCEPTION,
        SetList::PHPUNIT_SPECIFIC_METHOD,
        SetList::PHPUNIT_YIELD_DATA_PROVIDER,
    ]);

    $parameters->set(Option::EXCLUDE_RECTORS, [
        // Laravel classes are sometimes multi-purpose or magical
        UseInterfaceOverImplementationInConstructorRector::class,
        // Having unused parameters can increase clarity, e.g. in event handlers
        RemoveUnusedParameterRector::class,
    ]);
};
