<?php namespace Torann\Duct\Utilities;

use SplStack;

class PathStack
{
    protected $root;
    protected $paths;
    protected $extensions;

    function __construct($paths = array())
    {
        if (null === $this->root) {
            $this->root = base_path();
        }

        $this->paths = new SplStack;
        $this->extensions = new SplStack;

        if ($paths !== null) {
            $this->appendPaths($paths);
        }
    }

    function extensions()
    {
        return $this->extensions;
    }

    function paths()
    {
        return $this->paths;
    }

    function appendPaths($paths)
    {
        return $this->push($paths);
    }

    function prependPaths($paths)
    {
        return $this->unshift($paths);
    }

    function appendExtensions($extensions)
    {
        foreach ((array) $extensions as $ext) {
            $this->extensions->push(Path::normalizeExtension($ext));
        }

        return $this;
    }

    function prependExtensions($extensions)
    {
        foreach ((array) $extensions as $ext) {
            $this->extensions->unshift(Path::normalizeExtension($ext));
        }

        return $this;
    }

    function push($paths)
    {
        $paths = (array) $paths;

        foreach ($paths as $path) {
            $this->paths->push(rtrim($path, '\/'));
        }

        return $this;
    }

    function unshift($paths)
    {
        $paths = (array) $paths;

        foreach ($paths as $path) {
            $this->paths->unshift(rtrim($path, '\/'));
        }

        return $this;
    }

    function find($file)
    {
        foreach ($this->paths() as $loadPath)
        {
            $path = join(DIRECTORY_SEPARATOR, array($this->root, $loadPath, $file));

            if (file_exists($path)) {
                return $path;
            }
            else {
                foreach ($this->extensions() as $ext) {
                    if (file_exists($path . $ext)) {
                        return $path . $ext;
                    }
                }
            }
        }

        return false;
    }

    function __toString()
    {
        return join(PATH_SEPARATOR, iterator_to_array($this));
    }

    function toArray()
    {
        return iterator_to_array($this->paths);
    }
}

