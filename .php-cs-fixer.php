<?php declare(strict_types=1);

use function MLL\PhpCsFixerConfig\risky;

$finder = PhpCsFixer\Finder::create()
    ->notPath('vendor')
    ->in(__DIR__)
    ->name('*.php')
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
]);
