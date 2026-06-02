<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace sup\craftgeo\controllers;

use craft\web\Controller;
use sup\craftgeo\Geo;
use yii\web\Response as YiiResponse;

class SettingsController extends Controller
{
	public function actionIndex (): YiiResponse
	{
		return $this->renderTemplate(
			'geo/_settings',
			[
				'settings' => Geo::getInstance()->getSettings(),
			]
		);
	}
}
