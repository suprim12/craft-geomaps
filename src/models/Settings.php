<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use Override;

class Settings extends Model
{

    public ?string $mapToken = null;
    public ?string $geoToken = null;

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['mapToken', 'geoToken'], 'string'],
            [['mapToken', 'geoToken'], 'default', 'value' => null],
        ]);
    }
    public function getMapToken(): ?string
    {
        return $this->mapToken 
            ? $this->_parseEnv($this->mapToken) 
            : null;
    }

     public function getGeoToken(): ?string
    {
        return $this->geoToken 
            ? $this->_parseEnv($this->geoToken) 
            : null;
    }

    private function _parseEnv ($value): array|bool|string|null
    {
        if (is_string($value))
            return App::parseEnv($value);

        return array_map(function ($v) {
            return App::parseEnv($v);
        }, $value);
    }
}
