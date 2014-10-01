<?php namespace Torann\Duct\Processors;

class Import extends AbstractProcessor
{
    const IMPORT_PATTERN = '/
        @import[\s]*(?:"([^"]+)"|url\\((.+)\\));
    /xm';

    function render($context = null)
    {
        return preg_replace_callback(self::IMPORT_PATTERN, function($matches) use ($context) {

            if (!empty($matches[1])) {
                $path = $matches[1];
            }
            else if (!empty($matches[2])) {
                $path = $matches[2];
            }

            $resolvedPath = $context->resolve($path);

            if (!$resolvedPath) {
                return $matches[0];
            }

            $context->dependOn($resolvedPath);

            # Import source code without processing, for LESS files.
            return $context->evaluate($resolvedPath, array('processors' => array())) . "\n";

        }, $this->getData());
    }
}
