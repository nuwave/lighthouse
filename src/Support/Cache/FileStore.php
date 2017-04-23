<?php

namespace Nuwave\Lighthouse\Support\Cache;

use Illuminate\Support\Collection;

class FileStore
{
    /**
     * Store data in cache.
     *
     * @param  string $name
     * @param  mixed  $data
     * @return void
     */
    public function store($name, $data)
    {
        $path = $this->getPath($name);

        $this->makeDir(dirname($path));

        return file_put_contents($path, serialize($data));
    }

    /**
     * Retrieve data from cache.
     *
     * @param  string $name
     * @return mixed|null
     */
    public function get($name)
    {
        if (file_exists($this->getPath($name))) {
            return unserialize(file_get_contents($this->getPath($name)));
        }
    }

    /**
     * Remove the cache directory.
     *
     * @return void
     */
    public function flush()
    {
        $path = $this->getPath('');

        if (file_exists($path)) {
            Collection::make(array_diff(scandir($path), ['..', '.', '.gitignore']))->each(function ($file) {
                unlink($this->getPath($file));
            });
        }
    }

    /**
     * Get path name of item.
     *
     * @param  string $name
     * @return string
     */
    protected function getPath($name)
    {
        return config('lighthouse.cache').'/'.strtolower($name);
    }

    /**
     * Make a directory tree recursively.
     *
     * @param  string $dir
     * @return void
     */
    public function makeDir($dir)
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
