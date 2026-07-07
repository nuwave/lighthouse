<?php declare(strict_types=1);

/**
 * config-keys.php — list the configuration keys defined in src/lighthouse.php
 * without booting Laravel, for drift checks against lighthouse-config-and-flags.
 *
 * Why token-based, not `require`: src/lighthouse.php calls base_path()/env(), so a
 * bare `require` fatals outside a Laravel app. This parses the file with the PHP
 * tokenizer and prints every single-quoted string that is immediately followed by
 * `=>` (i.e. array keys), which approximates the config option names.
 *
 * Usage:
 *   php scripts/config-keys.php [path/to/lighthouse.php]
 * Default path: src/lighthouse.php relative to the current directory.
 *
 * Verified: executed with PHP 8.4 against src/lighthouse.php on 2026-07-07; prints
 * the config keys (route, schema_cache, query_cache, batchload_relations, ...).
 */

$path = $argv[1] ?? 'src/lighthouse.php';
if (! is_file($path)) {
    fwrite(STDERR, "File not found: {$path}\n");
    exit(1);
}

$tokens = token_get_all(file_get_contents($path));
$keys = [];
for ($i = 0, $n = count($tokens); $i < $n; $i++) {
    $tok = $tokens[$i];
    if (! is_array($tok) || $tok[0] !== T_CONSTANT_ENCAPSED_STRING) {
        continue;
    }
    // Look ahead past whitespace for a `=>` double arrow.
    $j = $i + 1;
    while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
        $j++;
    }
    if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_DOUBLE_ARROW) {
        $keys[] = trim($tok[1], "'\"");
    }
}

$keys = array_values(array_unique($keys));
sort($keys);
echo implode("\n", $keys), "\n";
fwrite(STDERR, count($keys) . " config keys found in {$path}\n");
