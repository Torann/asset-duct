<?php namespace Torann\Duct\Utilities;

class Path
{
    /**
     * Checks if the given path is absolute, regardless
     * if the file exists or not.
     *
     * @param  string $path - Path to check if absolute.
     * @returns bool
     */
    static function isAbsolute($path)
    {
        if (realpath($path) === $path) {
            return true;
        }

        if (empty($path)) {
            return false;
        }

        // Absolute Path (Unix) or Absolute UNC Path.
        if ($path[0] === '/' or $path[0] === '\\') {
            return true;
        }

        // If the path starts with a drive letter or "\\" (Windows):
        if (static::isWindows() and preg_match('#^([a-z]\:)?\\\\{1,2}#i', $path)) {
            return true;
        }

        return false;
    }

    /**
     * Is system running on windows (shame on you!).
     *
     * @returns bool
     */
    static protected function isWindows()
    {
        return "WIN" == strtoupper(substr(PHP_OS, 0, 3));
    }

    /**
     * Standardizes file extensions.
     *
     * @param   string  $extension
     * @returns string
     */
    static function normalizeExtension($extension)
    {
        $extension = strtolower($extension);

        if ('.' != $extension[0]) {
            $extension = ".$extension";
        }
        return $extension;
    }

    /**
     * Transform array into path.
     *
     * @returns string
     */
    static function join()
    {
        return join(DIRECTORY_SEPARATOR, func_get_args());
    }

    /**
     * Standardizes a given path.
     *
     * @returns string
     */
    static function clean($path)
    {
        $path = str_replace(['\\', '/'], ['/', DIRECTORY_SEPARATOR], $path);
        $path = trim($path, DIRECTORY_SEPARATOR);

        return DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR;
    }

    /**
     * Checks if the destination file is older than the
     * source file.
     *
     * @param   string  $src  - Source file path.
     * @param   string  $dest - Destination file path.
     * @returns bool
     */
    static function isUpToDate($src, $dest)
    {
        if (!file_exists($dest)) {
            return false;
        }

        if (filemtime($dest) >= filemtime($src)) {
            return true;
        }

        return false;
    }
}
