<?php namespace Torann\Duct\Processors;

use Less_Parser;
use Torann\Duct\Utilities\Path;

class LessParser extends AbstractProcessor
{
    function render($subContext)
    {
        // Less parser options
        $options = array(
            'sourceMap'        => (!empty($subContext->source_map)),
            'sourceMapWriteTo' => Path::join(public_path(), $subContext->source_map),
            'sourceMapURL'     => '/'.Path::join($subContext->source_map),
            'import_dirs'      => array()
        );

        // Get import directories
        $import = $subContext->manager->getConfig('less_import_dirs');

        foreach ($import as $dir=>$key)
        {
            $full_path = Path::join(base_path(), $dir).DIRECTORY_SEPARATOR;

            $options['import_dirs'][$full_path] = $key;
        }

        // Create new LESS instance
        $parser = new Less_Parser($options);

        // Return CSS
        $parser->parseFile($subContext->path);
        $css = $parser->getCss();

        // Update images in the CSS
        return preg_replace_callback("/url\(['|\"]?\/(.*?)['|\"]?\)/s", function($matches) use ($subContext)
        {
            // Get an asset from the manifest
            $image = $subContext->manager->getAsset($matches[1]);

            return str_replace($matches[1], $image, $matches[0]);

        }, $css);

    }
}
