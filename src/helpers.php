<?php

if ( ! function_exists('get_asset'))
{
	/**
	 * Return the asset's path.
	 *
	 * @param  string  $asset
	 * @return string
	 */
	function get_asset($asset)
	{
		return Duct::getAsset($asset);
	}
}
