<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\web;

use Craft;
use sup\craftgeo\Geo;

class GeoVariable{
    public function getMapToken (): string
	{
		$settings = Geo::getInstance()->getSettings();
		return $settings->getMapToken();
	}
}