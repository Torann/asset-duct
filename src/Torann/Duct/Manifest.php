<?php namespace Torann\Duct;

use Illuminate\Filesystem\Filesystem;

class Manifest {

    /**
     * Illuminate filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Path to the manifest file.
     *
     * @var string
     */
    protected $path;

    /**
     * Collection of manifest entries.
     *
     * @var array
     */
    protected $entries = array();

    /**
     * Create a new manifest instance.
     *
     * @param  Filesystem  $files
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Get a filename's hash from the manifest.
     *
     * @param  string      $filename
     * @param  mixed       $default
     * @return null|string
     */
    public function get($filename, $default = null)
    {
        return isset($this->entries[$filename]) ? $this->entries[$filename] : $default;
    }

    /**
     * Add a filename's hash to the manifest.
     *
     * @param  string  $filename
     * @param  string  $fingerprint
     * @return bool
     */
    public function add($filename, $fingerprint)
    {
        // Ensure it's a URL
        $filename = str_replace(DIRECTORY_SEPARATOR, '/', $filename);

        $this->entries[$filename] = $fingerprint;

        return $this->save();
    }

    /**
     * Loads and registers the manifest entries.
     *
     * @param string $path
     */
    public function load($path)
    {
        $this->path  = public_path() . "/{$path}/.manifest.json";

        if ($this->files->exists($this->path)) {
            $this->entries = json_decode($this->files->get($this->path), true);
        }
    }

    /**
     * Save the manifest.
     *
     * @return bool
     */
    public function save()
    {
        return (bool) $this->files->put($this->path, json_encode($this->entries));
    }

    /**
     * Delete the manifest.
     *
     * @return bool
     */
    public function delete()
    {
        $this->entries = array();

        if ($this->files->exists($this->path)) {
            return $this->files->delete($this->path);
        }
        else {
            return false;
        }
    }
}
