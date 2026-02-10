<?php declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->notPath('src/Tracing/FederatedTracing/Proto')
    ->notPath('vendor')
    ->notPath('phpstan-tmp-dir')
    ->ignoreDotFiles(false)
    ->ignoreVCS(true);

return MLL\PhpCsFixerConfig\risky($finder, [
    'general_phpdoc_annotation_remove' => [
        'annotations' => [
            'throws',
        ],
    ],
    'trailing_comma_in_multiline' => [
        'elements' => [
            'arguments',
            'arrays',
            'match',
            'parameters',
        ],
    ],
    'fully_qualified_strict_types' => [
        'phpdoc_tags' => [],
    ],
    'Laravel/laravel_phpdoc_alignment' => true,
])->registerCustomFixers([
    new Tests\LaravelPhpdocAlignmentFixer(),
]);
