<?php

declare(strict_types=1);

$env = getenv('APP_ENV');

$xmlContainerFile = 'dev' === $env
    ? dirname(__DIR__, 2).'/var/cache/dev/App_KernelDevDebugContainer.xml'
    : dirname(__DIR__, 2).'/var/cache/test/App_KernelTestDebugContainer.xml';

if (!file_exists($xmlContainerFile)) {
    throw new Exception(sprintf('phpstan depends on the meta information the symfony dependency injection that the compiler pass writes'.\PHP_EOL.'The meta xml file could not be found: %s'.\PHP_EOL.'To compile the container do a cache:clear in the current env (%s) with debug: true!', $xmlContainerFile, $env));
}

return [
    'parameters' => [
        'symfony' => [
            'containerXmlPath' => $xmlContainerFile,
        ],
    ],
];
