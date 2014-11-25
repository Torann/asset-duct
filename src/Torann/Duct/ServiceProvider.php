<?php namespace Torann\Duct;

use Illuminate\Foundation\AliasLoader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use Torann\Duct\Console\SetupCommand;
use Torann\Duct\Console\PublishCommand;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		// Register the package namespace
		$this->package('torann/duct');

		// Add 'Duct' facade alias
		AliasLoader::getInstance()->alias('Duct', 'Torann\Duct\Facade');

        // Register route for assets in no prod evn
        if ($this->app['torann.duct']->inDevelopment()) {
            $this->registerFilter();
        }
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
        $this->registerManifest();
        $this->registerDuct();
		$this->registerBladeExtensions();
		$this->registerCommands();
	}

    /**
     * Register the collection repository.
     *
     * @return void
     */
    protected function registerManifest()
    {
        $this->app['torann.manifest'] = $this->app->share(function($app)
        {
            return new Manifest($app['files']);
        });
    }

    /**
     * Register the asset manager.
     *
     * @return void
     */
    protected function registerDuct()
    {
        $this->app['torann.duct'] = $this->app->share(function($app)
        {
            // Read settings from config file
            $config = $app->config->get('duct::config', array());
            $config['public_dir'] = public_path();

            // Which environment
            $environment = 'local';
            if (in_array($app['env'], (array) array_get($config, 'production'))) {
                $environment = 'production';
            }
            if (in_array($app['env'], (array) array_get($config, 'staging'))) {
                $environment = 'staging';
            }

            // Create instance
            return new Manager($config, $app['torann.manifest'], $environment);
        });
    }

    /**
     * Register the Blade extensions with the compiler.
     *
     * @return void
     */
    protected function registerBladeExtensions()
    {
        $blade = $this->app['view']->getEngineResolver()->resolve('blade')->getCompiler();

        // JavaScript extension
        $blade->extend(function($value)
        {
            $matcher = "/(?<!\w)(\s*)@javascript\(['|\"](([[:alnum:]]|_)+.*)['|\"]((\,(.*))*)?\)/";
            return preg_replace_callback($matcher, function ($match)
            {
                return $match[1].$this->app['torann.duct']->bladeHtml($match[2]);
            }, $value);
        });

        // Stylesheet extension
        $blade->extend(function($value)
        {
            $matcher = "/(?<!\w)(\s*)@stylesheet\(['|\"](([[:alnum:]]|_)+.*)['|\"]((\,(.*))*)?\)/";
            return preg_replace_callback($matcher, function ($match)
            {
                return $match[1].$this->app['torann.duct']->bladeHtml($match[2]);
            }, $value);
        });

        // Image extension
        $blade->extend(function($value)
        {
            $matcher = "/@image\(['|\"](([[:alnum:]]|_|\/)+.*)['|\"]\)/";
            return preg_replace_callback($matcher, function ($match)
            {
                return $this->app['torann.duct']->bladeImage($match[1]);
            }, $value);
        });
    }

    /**
     * Extend HTML.
     *
     * @return void
     */
    public function registerHtmlExtenders()
    {
        $this->app['html'] = $this->app->share(function ($app)
        {
            // Read settings from config file
            $enabled = $app['config']->get('app.cachebuster::enabled', false);

            return new Builder($app['url'], $app['files'], $enabled);
        });
    }

    /**
     * Register the commands.
     *
     * @return void
     */
    public function registerCommands()
    {
        $this->app['duct.setup'] = $this->app->share(function($app)
        {
            return new SetupCommand();
        });

        $this->app['duct.publish'] = $this->app->share(function($app)
        {
            return new PublishCommand($app['torann.duct'], $app['files']);
        });

        $this->commands('duct.setup');
        $this->commands('duct.publish');
    }

    /**
     * Register missing asset filter.
     *
     * TODO: Clean this up, there has to be a better way
     *
     * @return void
     */
    private function registerFilter()
    {
        // Duct instance
        $manager = $this->app['torann.duct'];

        // Add before filter for static assets
        $this->app->before(function ($request, $response) use ($manager)
        {
            $asset_dir = $manager->getConfig('asset_dir');

            // Request path
            $path = str_replace($asset_dir['local'], $asset_dir['production'], $request->path());

            // Check production path
            if (starts_with($path, $asset_dir['production']))
            {
                $source_path = $manager->getAssetSource($path, $asset_dir['production']);

                if ($source_path) {
                    return new BinaryFileResponse($source_path, 200);
                }
            }
        });
    }

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
