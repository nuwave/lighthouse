<?php declare(strict_types=1);

use Tests\LaravelPhpdocAlignmentFixer;

use function MLL\PhpCsFixerConfig\risky;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->notPath('vendor')
    ->ignoreDotFiles(false)
    ->ignoreVCS(true);

return risky($finder, [
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
    'Laravel/laravel_phpdoc_alignment' => true,
])->registerCustomFixers([new LaravelPhpdocAlignmentFixer()]);
