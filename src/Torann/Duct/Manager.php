<?php namespace Torann\Duct;

use Illuminate\Support\Facades\HTML;

use Torann\Duct\Utilities\PathStack;
use Torann\Duct\Utilities\Path;

class Manager implements \ArrayAccess
{
    /**
     * Stack of Load Paths for Assets
     *
     * @var \Torann\Duct\Utilities\PathStack
     */
    public $loadPaths;

    /**
     * Map of file extensions to types
     *
     * @var array
     */
    public $contentTypes;

    /**
     * Processors are like engines, but are associated with
     * a mime type
     */
    public $preProcessors;
    public $postProcessors;
    public $bundleProcessors;

    /**
     * Package Config
     *
     * @var array
     */
    protected $config = array();

    /**
     * Assets manifest instance.
     *
     * @var \Torann\Duct\Manifest
     */
    protected $manifest;

	/**
	 * Production Environment
     *
	 * @var bool
	 */
	protected $production = false;

	/**
	 * Class constructor.
	 *
	 * @param  array     $config
     * @param  Manifest  $manifest
     * @param  bool      $production
	 */
	function __construct(array $config, Manifest $manifest, $production = false)
	{
        // Set config
        $this->config     = $config;
        $this->production = $production;
        $this->manifest   = $manifest;

        // Set contact types
        $this->contentTypes = $config['contentTypes'];

        // Set paths
        $this->loadPaths = new PathStack($this->config['paths']);

        // Enable resolving logical paths without extension.
        $this->loadPaths->appendExtensions(array_keys($this->contentTypes));

        $this->preProcessors    = new ProcessorRegistry;
        $this->postProcessors   = new ProcessorRegistry;
        $this->bundleProcessors = new ProcessorRegistry;

        // Register default preprocessors
        $this->preProcessors->register('text/css', '\\Torann\\Duct\\Processors\\Import');
        $this->preProcessors->register('text/css', '\\Torann\\Duct\\Processors\\Directive');
        $this->preProcessors->register('application/javascript', '\\Torann\\Duct\\Processors\\Directive');

        // Register default preprocessors
        $this->postProcessors->register('application/javascript', '\\Torann\\Duct\\Processors\\SafetyColons');

        // Register third-party post processors
        foreach ($this->getConfig('postprocessors') as $id=>$class) {
            $this->postProcessors->register($id, $class);
        }

        // Register compressors
        foreach ($this->getConfig('compressors') as $id=>$class) {
            $this->bundleProcessors->register($id, $class);
        }
	}

    /**
     * Return manifest instance.
     *
     * @return Manifest
     */
    public function getManifest()
    {
        return $this->manifest;
    }

    /**
     * Get a config value.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function getConfig($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * In production mode.
     *
     * @return bool
     */
    public function inProduction()
    {
        return $this->production;
    }

    /**
     * Return assets target path.
     *
     * @return string
     */
    public function getTargetPath()
    {
        return join(DIRECTORY_SEPARATOR, array(public_path(), $this->getConfig('asset_dir')));
    }

    /**
     * Returns the content type for the extension, .e.g. "application/javascript"
     * for ".js".
     *
     * @param  string $extension
     * @return string
     */
    public function contentType($extension)
    {
        $extension = Path::normalizeExtension($extension);

        return isset($this->contentTypes[$extension]) ? $this->contentTypes[$extension] : null;
    }

    /**
     * Finds the logical path in the stack of load paths
     * and returns the Asset.
     *
     * @param  string $logicalPath - path relative to the load path.
     * @return Asset
     */
    public function find($logicalPath)
    {
        if (Path::isAbsolute($logicalPath))
        {
            $realPath = $logicalPath;
        }
        else {
            $realPath = $this->loadPaths->find($logicalPath);
        }

        if (!is_file($realPath) || null === $realPath) {
            return;
        }

        return new Asset($this, $realPath, $logicalPath);
    }

    /**
     * Add from on of more collections
     *
     * @param  string   $logicalPath
     * @param  string   $extensionType
     * @param  boolean  $production (used in the console)
     * @return string
     */
    public function render($logicalPath, $production = false)
    {
        // Render as Production
        if ($production) {
            $this->production = $production;
        }

        // Get asset
        $asset = $this->find($logicalPath);

        if ($asset)
        {
            // Build
            $relative = $asset->write();

            if ($relative)
            {
                switch($asset->getContentType())
                {
                    case 'text/css':
                        return HTML::style($relative);
                        break;
                    case 'application/javascript':
                        return HTML::script($relative);
                        break;
                }

                return "<!-- torann\duct:: Content type for '{$logicalPath}' not found -->\n";
            }
        }

        return "<!-- torann\duct:: '{$logicalPath}' not found -->\n";
    }

    /**
     * Create the blade call for non-production environments
     *
     * @param  string   $path
     * @return string
     */
    public function bladeHtml($path)
    {
        return $this->production ? $this->render($path) : "<?php echo Duct::render('{$path}'); ?>";
    }

    /**
     * Create the blade call for non-production environments
     *
     * @param  string   $logicalPath
     * @return string
     */
    public function bladeImage($logicalPath)
    {
        // Production environments
        if($this->production) {
            return $this->getAsset($logicalPath);
        }

        return "<?php echo Duct::getAsset('{$logicalPath}'); ?>";
    }

    /**
     * Return the matching asset from the manifest
     *
     * @param  string  $path
     * @return array
     */
	public function getAsset($path)
	{
        $asset_dir = $this->getConfig('asset_dir');

        // Remove query string
        $path = preg_replace('/\?.*/', '', $path);

        // Must be absolute
        if ($path[0] !== '/') {
            $path = "/{$path}";
        }

        // Check manifest for production fingerprints
        if ($this->production && $this->getConfig('enable_static_file_fingerprint')) {
            $path = $this->manifest->get($path) ?: $path;
        }

        return asset("/{$asset_dir}{$path}");
    }

    /**
     * Return the source asset for file
     *
     * @param  string  $path
     * @return array
     */
	public function getAssetSource($path)
	{
        $asset_dir    = $this->getConfig('asset_dir');
        $static_files = $this->getConfig('static_files');

        $path = preg_replace("#^{$asset_dir}/?#", '', $path);
        $dir = dirname($path);

        if (isset($static_files[$dir]))
        {
            foreach ($static_files[$dir] as $source)
            {
                $source_path =  join(DIRECTORY_SEPARATOR, array(base_path(), $source, basename($path)));

                if (file_exists($source_path)) {
                    return $source_path;
                }
            }
        }

        return false;
    }

    /**
     * Sugar for find()
     *
     * @param  string $logicalPath
     * @return Asset
     */
    function offsetGet($logicalPath)
    {
        return $this->find($logicalPath);
    }

    function offsetSet($offset, $value) {}
    function offsetExists($offset) {}
    function offsetUnset($offset) {}
}
