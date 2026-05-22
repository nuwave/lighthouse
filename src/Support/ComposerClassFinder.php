<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support;

use Composer\Autoload\ClassLoader;

class ComposerClassFinder
{
    /**
     * Find class-strings declared directly in the given namespace using Composer.
     *
     * @return list<class-string>
     */
    public static function directClassesInNamespace(string $namespace): array
    {
        $namespace = rtrim($namespace, '\\') . '\\';
        $loader = self::composerClassLoader();

        /** @var array<class-string, true> $classes */
        $classes = [];

        // Authoritative classmap (populated by `composer dump-autoload --optimize`).
        foreach ($loader->getClassMap() as $fqcn => $_) {
            if (! str_starts_with($fqcn, $namespace)) {
                continue;
            }

            if (str_contains(substr($fqcn, strlen($namespace)), '\\')) {
                continue;
            }

            if (class_exists($fqcn)) {
                $classes[$fqcn] = true;
            }
        }

        // PSR-4 prefix map: scan the matching directory's direct *.php children.
        foreach ($loader->getPrefixesPsr4() as $prefix => $directories) {
            if (! str_starts_with($namespace, $prefix)) {
                continue;
            }

            $sub = str_replace('\\', DIRECTORY_SEPARATOR, substr($namespace, strlen($prefix)));
            foreach ($directories as $directory) {
                $target = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $sub;
                if (! is_dir($target)) {
                    continue;
                }

                foreach (\Safe\scandir($target) as $entry) {
                    if (! str_ends_with($entry, '.php')) {
                        continue;
                    }

                    /** @var class-string $fqcn */
                    $fqcn = $namespace . substr($entry, 0, -4);
                    if (class_exists($fqcn)) {
                        $classes[$fqcn] = true;
                    }
                }
            }
        }

        /** @var list<class-string> $result */
        $result = array_keys($classes);

        return $result;
    }

    protected static function composerClassLoader(): ClassLoader
    {
        foreach (spl_autoload_functions() ?: [] as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof ClassLoader) {
                return $autoloader[0];
            }
        }

        throw new \RuntimeException('Composer ClassLoader was not found among registered autoloaders.');
    }
}
