<?php namespace Torann\Duct\Compressor;

use Minify_CSS;

class UglifyCss extends AbstractCompressor
{
    static function getDefaultContentType()
    {
        return "text/css";
    }

    public function render($data, $vars = array())
    {
        return Minify_CSS::minify($this->data, array('preserveComments' => false));
    }
}
