<?php namespace Torann\Duct\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SetupCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'duct:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Setup the default Asset Duct folders in your new Laravel project";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $this->line('');
        $this->line('Creating initial directory structure.');
        $this->line('');

        $this->copyStructure();

        $this->line('');
        $this->line('Finished');
    }

    private function copyStructure()
    {
        $source = realpath(__DIR__ . '/../../../../structure');
        $dest   = base_path();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item)
        {
            $path = join(DIRECTORY_SEPARATOR, array($dest, $iterator->getSubPathName()));

            if ($item->isDir())
            {
                if (!is_dir($path)) {
                    mkdir($path);
                }
            }
            else {
                copy($item, $path);
                $this->line('   Copying -> ' . str_replace($dest, '', $path));
            }
        }
    }
}