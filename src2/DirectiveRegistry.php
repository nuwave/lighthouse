<?php


namespace Nuwave\Lighthouse;


use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class DirectiveRegistry
{
    /** @var \Illuminate\Support\Collection */
    protected $directives;

    /**
     * DirectiveRegistry constructor.
     */
    public function __construct()
    {
        $this->directives = collect();
    }


    public function add(string $class) : DirectiveRegistry
    {
        $this->directives->push(app($class));

        return $this;
    }

    public function has(string $name) : bool
    {
        return $this->get($name)->isNotEmpty();
    }

    public function get(string $name) : Collection
    {
        return $this->directives->filter(function ($directive) use ($name) {
            return $directive->name() === $name;
        });
    }

    /**
     * Gets the directive resolvers from a collection of directives.
     *
     * It will return all the directive resolvers which are in the collection of
     * directives. Afterwards it sorts the collection of resolvers, so they are
     * returned in the order the directives are supplied.
     *
     * @param Collection $directives
     * @return Collection
     */
    public function getFromDirectives(Collection $directives) : Collection
    {
        return $this->directives->filter(function (Directive $directive) use ($directives) {
            return $directives->first(function ($directiveType) use ($directive) {
                return $directiveType->name() === $directive->name();
            });
        })->sort(function (Directive $directive) use ($directives) {
            return $directives->filter(function ($directiveType) use ($directive) {
                return $directiveType->name() === $directive->name();
            })->keys()->first();
        });
    }

    /**
     * Register all of the commands in the given directory.
     *
     * https://github.com/laravel/framework/blob/5.5/src/Illuminate/Foundation/Console/Kernel.php#L190-L224
     *
     * @param array|string $paths
     * @param null $namespace
     */
    public function load($paths, $namespace = null)
    {
        $paths = array_unique(is_array($paths) ? $paths : (array) $paths);
        $paths = array_filter($paths, function ($path) {
            return is_dir($path);
        });
        if (empty($paths)) {
            return;
        }
        $namespace = $namespace ?? app()->getNamespace();

        $path = starts_with($namespace, 'Nuwave\\Lighthouse')
            ? realpath(__DIR__)
            : app_path();

        foreach ((new Finder)->in($paths)->files() as $directive) {
            $directive = $namespace.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    Str::after($directive->getPathname(), $path.DIRECTORY_SEPARATOR)
                );

            $reflection = new ReflectionClass($directive);
            if($reflection->implementsInterface(Directive::class) &&
                !$reflection->isAbstract()) {
                $this->add($directive);
            }
        }
    }
}