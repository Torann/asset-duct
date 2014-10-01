<?php namespace Torann\Duct\Processors;

use Less_Parser;

class LessParser extends AbstractProcessor
{
    function render($subContext)
    {
        // Create new LESS instance
        $parser = new Less_Parser();

        // Return CSS
        $parser->parseFile($subContext->path);
        $css = $parser->getCss();

        // Get asset directory
        $asset_dir = $subContext->manager->getConfig('asset_dir');

        // Update images in the CSS
        return preg_replace_callback("/url\(['|\"]?(.*?)['|\"]?\)/s", function($matches) use ($subContext)
        {
            // Get an asset from the manifest
            $image = $subContext->manager->getAsset($matches[1]);

            return str_replace($matches[1], $image, $matches[0]);

        }, $css);

    }
}
