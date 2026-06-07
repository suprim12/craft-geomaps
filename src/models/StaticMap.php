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
use sup\craftgeo\Geo;
use sup\craftgeo\services\GeoService;

class StaticMap extends Model
{
    public mixed  $center         = null;
    public mixed  $centerFallback = null;
    public int    $width          = 640;
    public int    $height         = 400;
    public int    $zoom           = 14;
    public int    $scale          = 1;
    public array  $markers        = [];
    public string $format         = 'png';
    public string $mapType        = 'roadmap';
    public mixed $colorScheme        = null;
    private ?string $_resolvedCenter = null;

    public function img(array $options = []): ?string
    {
        $this->setOptions($options);
        $this->_resolvedCenter = null;
        return $this->buildUrl(1);
    }

    public function imgSrcSet(array $options = []): ?string
    {
        $this->setOptions($options);
        $this->_resolvedCenter = null;
        $this->_resolvedCenter = $this->resolveCenter();
        if ($this->_resolvedCenter === null) {
            return null;
        }
        $url1x = $this->buildUrl(1);
        $url2x = $this->buildUrl(2);

        if (!$url1x || !$url2x) {
            return null;
        }

        return "{$url1x} 1x, {$url2x} 2x";
    }

    private function setOptions(array $options): void
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    private function buildUrl(int $scale = 1): ?string
    {
        $settings = Geo::getInstance()->getSettings();
        $apiKey   = $settings->getMapToken();

        if (!$apiKey) {
            Craft::warning('GeoMaps: mapToken is required for static maps', 'geo');
            return null;
        }
        $center = $this->_resolvedCenter ?? $this->resolveCenter();

        if (!$center) {
            Craft::warning('GeoMaps: could not resolve center for static map', 'geo');
            return null;
        }

        $params = [
            'key'     => $apiKey,
            'size'    => $this->width . 'x' . $this->height,
            'zoom'    => $this->zoom,
            'scale'   => $scale,
            'format'  => $this->format,
            'center'  => $center,
            'maptype' => $this->mapType ?? 'roadmap',
            'mapId' => "DEMO_MAP_ID",
            'colorScheme' => $this->colorScheme ?? null
        ];

        // Build markers
        $markerParams = $this->buildMarkerParams($center);

        $query = http_build_query($params);

        foreach ($markerParams as $marker) {
            $query .= '&markers=' . urlencode($marker);
        }

        return 'https://maps.googleapis.com/maps/api/staticmap?' . $query;
    }

    private function resolveCenter(): ?string
    {
        $center = $this->center;

        if (is_array($center)) {
            $lat = $center['lat'] ?? $center[0] ?? null;
            $lng = $center['lng'] ?? $center[1] ?? null;

            if (is_numeric($lat) && is_numeric($lng)) {
                return "{$lat},{$lng}";
            }
        }

        if (is_string($center) && !empty(trim($center))) {
            return urlencode(trim($center));
        }

        if ($this->centerFallback) {
            $fb = $this->centerFallback;

            $lat = $fb['lat'] ?? $fb[0] ?? null;
            $lng = $fb['lng'] ?? $fb[1] ?? null;

            if (is_numeric($lat) && is_numeric($lng)) {
                return "{$lat},{$lng}";
            }
        }

        return null;
    }

    private function buildMarkerParams(string $center): array
    {
        $markers = $this->markers;

        if (empty($markers)) {
            return ["color:red|{$center}"];
        }

        $params = [];

        foreach ($markers as $marker) {
            $parts = [];

            $color = $marker['color'] ?? '#ff0000';
            $color = ltrim($color, '#');
            $parts[] = "color:0x{$color}";

            if (!empty($marker['label'])) {
                $label   = strtoupper(substr($marker['label'], 0, 1));
                $parts[] = "label:{$label}";
            }

            $location = $marker['location'] ?? null;

            if (is_array($location)) {
                $lat = $location['lat'] ?? $location[0] ?? null;
                $lng = $location['lng'] ?? $location[1] ?? null;
                if (is_numeric($lat) && is_numeric($lng)) {
                    $parts[] = "{$lat},{$lng}";
                }
            } elseif (is_string($location) && !empty($location)) {
                $parts[] = urlencode($location);
            } else {
                $parts[] = $center;
            }

            $params[] = implode('|', $parts);
        }

        return $params;
    }
}