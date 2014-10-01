<?php namespace Torann\Duct;

use UnexpectedValueException;
use Torann\Duct\Utilities\Path;

class Context
{
    public $path;

    // All paths which were already required.
    public $requiredPaths    = array();

    // Array of all dependency paths.
    public $dependencyPaths  = array();

    // Array of the dependencies' contents.
    public $dependencyAssets = array();
    public $manager;

    function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Add a dependency on a path.
     *
     * These dependencies are not included with the parent's body,
     * but are taken account of when the freshness of the asset is
     * checked.
     *
     * @param   string   $path - Logical path or relative path.
     * @returns Context
     */
    function dependOn($path)
    {
        $this->dependencyPaths[] = $this->resolve($path);

        return $this;
    }

    /**
     * Get content and run processors.
     *
     * @param   string   $path    - Logical path or relative path.
     * @param   array    $options - Set of options.
     * @returns string
     */
    function evaluate($path, $options = array())
    {
        if (isset($options['data'])) {
            $data = $options['data'];
        }
        else {
            if (! $path) {
                throw new UnexpectedValueException("Error parsing \"{$this->path}\"");
            }

            if (! is_file($path)) {
                throw new UnexpectedValueException("Asset \"{$path}\" not found");
            }

            $data = file_get_contents($path);
        }

        $subContext = $this->createSubContext($path);

        if (array_key_exists("processors", $options)) {
            $processors = (array) $options["processors"];
        }
        else {
            $processors = array();
        }

        foreach ($processors as $p)
        {
            $block = function() use (&$data) {
                return $data;
            };

            if (is_callable($p)) {
                $processor = $p($block);
            }
            else {
                $processor = new $p($block);
            }

            $subContext->path = $processor->source = $path;
            $data = $processor->render($subContext);
        }

        $this->dependencyPaths  = array_merge($this->dependencyPaths, $subContext->dependencyPaths);
        $this->dependencyAssets = array_merge($this->dependencyAssets, $subContext->dependencyAssets);

        return $data;
    }

    /**
     * Renders the asset as Data URI, excellent when including sprite images
     * directly in CSS files.
     *
     * @param  string   $path - Logical path or relative path (./<path>)
     * @returns string
     */
    function dataUri($path)
    {
        $data = $this->evaluate($this->resolve($path));

        return sprintf("data:%s;base64,%s",
            $this->contentType($path), rawurlencode(base64_encode($data))
        );
    }

    function contentType($path)
    {
        $asset = $this->manager->find($this->resolve($path));

        if (!$asset) {
            throw new \Exception("Asset '{$path}' not found.");
        }

        return $asset->getContentType();
    }

    /**
     * Adds an asset to the list of dependencies which should be included
     * with their body.
     *
     * @param  string   $path - Path relative to load path, relative path.
     * @returns Context
     */
    function requireAsset($path)
    {
        $resolvedPath = $this->resolve($path);

        if (null === $resolvedPath) {
            throw new \UnexpectedValueException("Asset '{$path}' not found");
        }

        $asset = $this->manager->find($resolvedPath);

        if (!in_array($resolvedPath, $this->requiredPaths)) {
            $this->dependOn($resolvedPath);

            $processors = is_callable(array($asset, "getProcessors")) ? $asset->getProcessors() : array();

            $this->dependencyAssets[] = $this->evaluate($resolvedPath, array(
                "processors" => $processors
            ), $path);

            $this->requiredPaths[] = $resolvedPath;
        }

        return $this;
    }

    /**
     * Requires all assets in the given directory.
     *
     * @param  string   $path - Directory which should be included.
     * @returns Context
     */
    function requireTree($path)
    {
        $resolved = $this->resolve($path);

        if (!$resolved) {
            throw new \InvalidArgumentException("Path '$path' not found.");
        }

        $dir = new \FilesystemIterator($resolved);

        foreach ($dir as $file)
        {
            // Ignore dotted files
            if (substr($file->getBasename(), 0, 1) !== '.') {
                $this->requireAsset($file->getRealpath());
            }
        }

        return $this;
    }

    function resolve($path)
    {
        // Skip the load path if the path starts with `./`
        if (preg_match('{^\.(/|\\\\)}', $path)) {
            $path = dirname($this->path) . DIRECTORY_SEPARATOR . preg_replace('{^\.(/|\\\\)}', '', $path);
        }

        // When resolving a directory either look for a file named
        // "$dir/index.$ext" or return the path to the directory (e.g.
        // for "require_tree").
        if (is_dir($path))
        {
            $index = join(DIRECTORY_SEPARATOR, array($path, "index{$this->getExtension()}"));

            if (file_exists($index)) {
                $path = $index;
            }
            else {
                if (Path::isAbsolute($path)) {
                    return realpath($path);
                }

                return $this->manager->loadPaths->find($path);
            }
        }

        if (Path::isAbsolute($path)) {
            return realpath($path);
        }

        return $this->manager->loadPaths->find($path);
    }

    protected function getExtension()
    {
        return Path::normalizeExtension(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    protected function createSubContext()
    {
        $context = new static($this->manager);
        $context->path = $this->path;
        $context->requiredPaths =& $this->requiredPaths;

        return $context;
    }
}
