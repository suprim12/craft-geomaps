<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace sup\craftgeo\web;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


class GeoAsset extends AssetBundle
{

	public function init ()
	{
		$this->sourcePath = __DIR__;

		$this->depends = [
			CpAsset::class
		];


        $this->css = [
            'assets/css/supgeo.css'
        ];

        $this->js = [
            'assets/js/supgeo.js'
        ];

		parent::init();
	}

}
