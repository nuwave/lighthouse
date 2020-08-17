<?php

use Rector\Core\Configuration\Option;
use Rector\Set\ValueObject\SetList;
use Rector\SOLID\Rector\ClassMethod\UseInterfaceOverImplementationInConstructorRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $services = $containerConfigurator->services();

    $parameters->set(Option::SETS, [SetList::CODE_QUALITY]);

    $parameters->set(Option::EXCLUDE_RECTORS, [
        UseInterfaceOverImplementationInConstructorRector::class,
    ]);
};
