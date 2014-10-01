<?php namespace Torann\Duct\Compressor;

use JSMin;

class UglifyJs extends AbstractCompressor
{
    static function getDefaultContentType()
    {
        return "application/javascript";
    }

    public function render($data, $vars = array())
    {
        return JSMin::minify($this->data);
    }
}
