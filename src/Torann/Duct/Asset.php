<?php namespace Torann\Duct;

use Torann\Duct\Utilities\Path;

class Asset
{
    public $path;
    public $logicalPath;
    public $digest;

    // The asset's declared dependencies.
    public $dependencies = array();

    protected $manager;
    protected $body;

    // List of the file's extensions.
    protected $extensions;
    protected $contentType;

    // Cached format extension.
    protected $formatExtension;

    /**
     * Initializes the asset.
     *
     * @param Manager $manager     - The manager object.
     * @param string  $path        - The absolute path to the asset.
     * @param string  $logicalPath - The path relative to the manager.
     */
    public function __construct(Manager $manager, $path, $logicalPath = null)
    {
        $this->manager     = $manager;
        $this->path        = $path;
        $this->logicalPath = $logicalPath;
    }

    /**
     * Set content type
     *
     * @param  string $contentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * The determination of the asset's content type is up to the specific
     * implementation, and could be either derived from the file extension
     * or by looking into the file's contents.
     *
     * @return string|false
     */
    public function getContentType()
    {
        $ext = $this->getFormatExtension();

        return isset($this->manager->contentTypes[$ext])
            ? $this->manager->contentTypes[$ext]
            : false;
    }

    /**
     * Set body content
     *
     * @param string $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * Processes and stores the asset's body.
     *
     * @returns string
     */
    public function getBody()
    {
        if (null === $this->body)
        {
            $result = '';
            $ctx    = new Context($this->manager, $this->getSourceMap());
            $data   = file_get_contents($this->path);

            // Process the contents
            $body = $ctx->evaluate($this->path, array(
                "data"       => $data,
                "processors" => $this->getProcessors()
            ), $this->getLogicalPath());

            $this->dependencies = array_merge($this->dependencies, $ctx->dependencyPaths);

            $result .= join("\n", $ctx->dependencyAssets);
            $result .= $body;

            $this->body = $result;
        }

        return $this->body;
    }

    /**
     * Returns the asset's path relative to the manager.
     *
     * @return string
     */
    public function getLogicalPath()
    {
        return $this->logicalPath;
    }

    /**
     * Returns the asset's filename.
     *
     * @param  bool   $includeExtensions - Include extensions in the filename (default: true)
     * @return string
     */
    public function getBasename($includeExtensions = true)
    {
        $basename = basename($this->path);

        if (!$includeExtensions) {
            $basename = substr($basename, 0, strpos($basename, '.'));
        }

        return $basename;
    }

    /**
     * Returns the asset source map file
     *
     * @return string
     */
    public function getSourceMap()
    {
        $filename = $this->getBasename(false).'.map';

        return join(DIRECTORY_SEPARATOR, array($this->manager->getConfig('asset_dir'), $filename));
    }

    /**
     * Returns the asset path.
     *
     * @return string
     */
    public function getDirname()
    {
        return dirname($this->path);
    }

    /**
     * Return SHA1 of body content
     *
     * @return string
     */
    public function getDigest()
    {
        if (null === $this->digest) {
            $this->digest = sha1($this->getBody());
        }

        return $this->digest;
    }

    /**
     * Calculates the date when this asset and its dependencies
     * were last modified.
     *
     * @returns int
     */
    public function getLastModified()
    {
        // Load the asset, if it's not loaded
        if (null === $this->body) {
            $this->getBody();
        }

        $dependenciesLastModified = array_merge(array_map("filemtime", $this->dependencies), array(filemtime($this->path)));

        return max($dependenciesLastModified);
    }

    /**
     * Returns the asset's full path with digest.
     *
     * @return string
     */
    public function getDigestName()
    {
        $ext = $this->getFormatExtension();
        return preg_replace('/(\.\w+)$/', "-{$this->getDigest()}$ext", $this->logicalPath);
    }

    /**
     * Returns the filename with extension, that's appropriate
     * after the asset was processed.
     *
     * @param  bool   $includeHash
     * @return string
     *
     */
    public function getTargetName($includeHash = true)
    {
        $target = $this->getBasename(false);

        if ($includeHash) {
            $target .= '-' . $this->getDigest();
        }

        $target .= $this->getFormatExtension();

        return $target;
    }

    /**
     * Writes the asset's content to the directory.
     *
     * @return array
     */
    public function getProcessors()
    {
        $contentType = $this->getContentType();

        // General processors
        $processors = array_merge(
            $this->manager->preProcessors->all($contentType),
            $this->manager->postProcessors->all($contentType)
        );

        // For production mode
        if($this->manager->inProduction()) {
            $processors = array_merge($processors, $this->manager->bundleProcessors->all($contentType));
        }

        return $processors;
    }

    /**
     * Writes the asset's content to the directory.
     *
     * @return string
     */
    public function write()
    {
        // Asset root
        $dir = $this->manager->getConfig('asset_dir');

        // Check manifest first in production
        if ($cachedName = $this->inManifest()) {
            return join(DIRECTORY_SEPARATOR, array($dir, $cachedName));
        }

        // Destination
        $filename = ($this->manager->inProduction()) ? $this->getDigestName() : $this->getBasename();
        $dest     = join(DIRECTORY_SEPARATOR, array(public_path(), $dir, $filename));

        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0777, true);
        }

        // Parse
        $body = $this->getBody();

        @file_put_contents($dest, $body);

        // Add to manifest in production only
        if ($this->manager->inProduction()) {
            $manifest = $this->manager->getManifest();
            $manifest->add($this->getBasename(), $filename);
        }

        return join(DIRECTORY_SEPARATOR, array($dir, $filename));
    }

    /**
     * Check for file in the manifest.
     *
     * @return string|false
     */
    public function inManifest()
    {
        if ($this->manager->inProduction())
        {
            $manifest = $this->manager->getManifest();
            return $manifest->get($this->getBasename(), false);
        }

        return false;
    }

    /**
     * Determines the format extension.
     *
     * The format extension is the extension which is not
     * assigned to an engine and is present in the manager's
     * configured content types.
     *
     * @return string
     */
    public function getFormatExtension()
    {
        if (!$this->formatExtension)
        {
            $manager = $this->manager;

            $this->formatExtension = current(array_filter(
                $this->getExtensions(),
                function ($ext) use ($manager) {
                    return isset($manager->contentTypes[$ext]);
                }
            ));
        }

        return $this->formatExtension;
    }

    /**
     * Collects the file's extensions.
     *
     * @return array - normalized extensions.
     */
    protected function getExtensions()
    {
        if (null === $this->extensions)
        {
            $basename = $this->getBasename();

            // Avoid treating name of a dotfile as extension by
            // ignoring dots at the first offset in the string
            if (!$basename or false === ($pos = strpos($basename, '.', 1))) {
                return array();
            }

            $extensions = explode('.', substr($basename, $pos + 1));

            $this->extensions = array_map(function($ext) {
                return Path::normalizeExtension($ext);
            }, $extensions);
        }

        return $this->extensions;
    }

    /**
     * Convert object to string
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->getBody();
        }
        catch (\Exception $e) {
            return '';
        }
    }
}
