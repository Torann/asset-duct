<?php namespace Torann\Duct\Console;

use SplFileInfo;

use Torann\Duct\Manager;
use Torann\Duct\Utilities\Path;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class PublishCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'duct:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish assets';

    /**
     * Asset Manager instance.
     *
     * @var \Torann\Duct\Manager
     */
    protected $manager;

    /**
     * Illuminate filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Asset directory.
     *
     * @var string
     */
    protected $asset_path;

    /**
     * Array of static files.
     *
     * @var array
     */
    protected $static_files;

    /**
     * Assets manifest instance.
     *
     * @var \Torann\Duct\Manifest
     */
    protected $manifest;

    /**
     * Production state.
     *
     * @var bool
     */
    protected $production = false;

    /**
     * Create a new asset compile command instance.
     *
     * @param  \Torann\Duct\Manager  $manager
     * @param  \Illuminate\Filesystem\Filesystem  $files
     */
    public function __construct(Manager $manager, Filesystem $files)
    {
        parent::__construct();

        $this->manager  = $manager;
        $this->files    = $files;
        $this->manifest = $manager->getManifest();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $useFingerprints = $this->manager->getConfig('enable_static_file_fingerprint');

        if ($this->input->getOption('prod'))
        {
            $this->production = true;

            // Is production publish
            $this->manager->setProduction();

            if ($useFingerprints) {
                $this->verboseOutput('<info>Publishing production assets with fingerprints</info>');
            }
            else {
                $this->verboseOutput('<info>Publishing production assets</info>');
            }
        }
        else {
            $this->verboseOutput('<info>Publishing development assets</info>');
        }

        // Set paths
        $this->asset_path   = $this->manager->getTargetPath();
        $this->static_files = $this->manager->getConfig('static_files');

        // Cleanup
        $this->cleanEverything();

        // Copy assets
        $this->copyAssets($useFingerprints);

        // Compile assets
        if ($this->input->getOption('compile')) {
            $this->compileAssets();
        }

        // Remove old views
        if ($this->input->getOption('delete'))
        {
            $this->verboseOutput('<comment>Clearing compiled views...</comment>');
            array_map('unlink', glob(storage_path().'/views/*'));
        }
    }

    /**
     * Clean everything up.
     *
     * @return void
     */
    protected function cleanEverything()
    {
        // Clear manifest
        $this->verboseOutput('<comment>Clearing manifest...</comment>');
        $this->manifest->delete();

        // Remove assets
        $this->verboseOutput('<comment>Removeing old assets...</comment>');
        $this->files->deleteDirectory($this->asset_path, true);
    }

    /**
     * Copy assets to the proper directory.
     *
     * @param  bool $useFingerprints
     * @return void
     */
    protected function copyAssets($useFingerprints = false)
    {
        $this->info('Publishing static assets');

        $sourceRoot = base_path().DIRECTORY_SEPARATOR;

        foreach ($this->static_files as $destination => $sources)
        {
            // Ensure the destination is there
            $dest = join(DIRECTORY_SEPARATOR, array($this->asset_path, $destination));

            if (! is_dir($dest)) {
                mkdir($dest, 0777, true);
            }

            foreach ($sources as $source)
            {
                $sourcePath = $sourceRoot.$source;

                // Copy single file
                if(is_file($sourcePath))
                {
                    $name = basename($source);
                    $path = join(DIRECTORY_SEPARATOR, array($dest, $name));

                    $this->copyFile(new SplFileInfo($sourcePath), $path, $destination, $name, $useFingerprints);

                    continue;
                }

                // Get directory items
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item)
                {
                    $name = $iterator->getSubPathName();

                    // No DOT files
                    if ($name[0] === '.') {
                        continue;
                    }

                    $path = join(DIRECTORY_SEPARATOR, array($dest, $name));

                    if ($item->isDir())
                    {
                        if (!is_dir($path)) {
                            mkdir($path, 0777, true);
                        }
                    }
                    else {

                        $this->copyFile($item, $path, $destination, $name, $useFingerprints);
                    }
                }
            }
        }
    }

    /**
     * Copy single file.
     *
     * @param  object $file
     * @param  string $path
     * @param  string $destination
     * @param  string $name
     * @param  bool   $useFingerprints
     * @return void
     */
    protected function copyFile($file, $path, $destination, $name, $useFingerprints = false)
    {
        // Create a fingerprint
        if ($this->production && $useFingerprints)
        {
            // Generate new name
            $digest = sha1($file->getMTime());

            $original = "/{$destination}/{$name}";

            // Generate new name
            $ext  = Path::normalizeExtension($file->getExtension());
            $name = preg_replace('/(\.\w+)$/', "-{$digest}$ext", $name);

            // Update path with new filename
            $path = join(DIRECTORY_SEPARATOR, array($this->asset_path, $destination, $name));

            // Add to manifest
            $this->manifest->add($original, "/{$destination}/{$name}");
        }

        // Copy that file
        $this->files->copy($file, $path);
        $this->verboseOutput('   Copying -> ' . str_replace($this->asset_path, '', $path));
    }

    /**
     * Compile assets.
     *
     * @return void
     */
    protected function compileAssets()
    {
        $this->info('Precompiling assets');

        $paths = $this->manager->getConfig('paths');

        foreach ($paths as $path)
        {
            $path = join(DIRECTORY_SEPARATOR, array(base_path(), $path, ''));

            $files = $this->files->glob("{$path}*.{css,js}", GLOB_BRACE);

            if ($files !== false)
            {
                foreach ($files as $file)
                {
                    $filename = str_replace($path, '', $file);
                    $this->verboseOutput('   Compiling -> ' . $filename);
                    $this->manager->render($filename);
                }
            }
        }
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('prod', 'p', InputOption::VALUE_NONE, 'Build assets for a production environment.'),
            array('compile', 'c', InputOption::VALUE_NONE, 'Precompile assets.'),
            array('delete', 'd', InputOption::VALUE_NONE, 'Delete compiled views.')
        );
    }

    /**
     * Out put to console if verbose.
     *
     * @return void
     */
    protected function verboseOutput($line)
    {
        if ($this->output->isVerbose())
        {
            $this->line($line);
        }
    }

}