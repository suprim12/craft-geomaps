<?php


/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace sup\craftgeo\integrations\feedme\fields;


use craft\feedme\base\Field;
use craft\feedme\base\FieldInterface;
use craft\feedme\helpers\DataHelper;
use Craft;
use craft\helpers\Json;
use sup\craftgeo\fields\GeoField;

class GeoFeedMeField extends Field implements FieldInterface{

	public static $name = 'Maps';
	public static $class = GeoField::class;


    public function getMappingTemplate(): string
    {
        return 'geo/feedme-field';
    }

//     // Define the sub-fields Feed Me will map to
    public function getMappingFields(): array
    {
        return [
            'lat'          => Craft::t('geo', 'Latitude'),
            'lng'          => Craft::t('geo', 'Longitude'),
            'zoom'         => Craft::t('geo', 'Zoom'),
            'full_address' => Craft::t('geo', 'Full Address'),
            'address1'     => Craft::t('geo', 'Address Line 1'),
            'suburb'       => Craft::t('geo', 'Suburb'),
            'postcode'     => Craft::t('geo', 'Postcode'),
            'country'      => Craft::t('geo', 'Country'),
            'geoJson'      => Craft::t('geo', 'GeoJSON'),
        ];
    }

    // public function parseField(): mixed
    // {
    //     $preppedData = [];

    //     $subFields = [
    //         'lat', 'lng', 'zoom',
    //         'full_address', 'address1',
    //         'suburb', 'postcode',
    //         'country', 'geoJson',
    //     ];

    //     foreach ($subFields as $key) {
    //         $value = DataHelper::fetchValue($this->feedData, $this->fieldHandle . '.' . $key);

    //         if ($value === null) {
    //             continue;
    //         }

    //         $preppedData[$key] = match ($key) {
    //             'lat', 'lng' => (float) $value,
    //             'zoom'       => (int)   $value,
    //             'country'    => strtoupper(substr(trim((string) $value), 0, 2)),
    //             'geoJson'    => $this->parseGeoJson($value),
    //             default      => trim((string) $value),
    //         };
    //     }

    //     if (empty($preppedData)) {
    //         $raw = DataHelper::fetchValue($this->feedData, $this->fieldHandle);
    //         if (!empty($raw)) {
    //             $preppedData = $this->parseRawValue($raw);
    //         }
    //     }

    //     return empty($preppedData) ? null : Json::encode($preppedData);
    // }

    public function parseField(): mixed
{
    $preppedData = [];

    $subFields = [
        'lat', 'lng', 'zoom',
        'full_address', 'address1',
        'suburb', 'postcode',
        'country', 'geoJson',
    ];

    foreach ($subFields as $key) {
        // Read the mapped feed node from options
        $node = $this->fieldInfo['options'][$key] ?? null;

        if (!$node) {
            continue;
        }

        $value = DataHelper::fetchValue($this->feedData, $node);

        if ($value === null) {
            continue;
        }

        $preppedData[$key] = match ($key) {
            'lat', 'lng' => (float) $value,
            'zoom'       => (int)   $value,
            'country'    => strtoupper(substr(trim((string) $value), 0, 2)),
            'geoJson'    => $this->parseGeoJson($value),
            default      => trim((string) $value),
        };
    }

    return empty($preppedData) ? null : Json::encode($preppedData);
}


    private function parseRawValue(mixed $value): array
    {
        // Already a JSON string
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = Json::decodeIfJson($value);
            if (is_array($decoded)) {
                return $this->castTypes($decoded);
            }
        }

        // "lat,lng" format
        if (is_string($value) && substr_count($value, ',') === 1) {
            [$lat, $lng] = explode(',', $value);
            return [
                'lat' => (float) trim($lat),
                'lng' => (float) trim($lng),
            ];
        }

        return [];
    }

    private function parseStringValue(string $key, mixed $value): mixed
    {
        // Validate country code
        if ($key === 'country') {
            return strtoupper(substr(trim((string) $value), 0, 2));
        }

        // Validate and normalise GeoJSON
        if ($key === 'geoJson') {
            $decoded = Json::decodeIfJson($value);
            return is_array($decoded) ? Json::encode($decoded) : null;
        }

        return trim((string) $value);
    }

    private function castTypes(array $data): array
    {
        if (isset($data['lat']))  $data['lat']  = (float) $data['lat'];
        if (isset($data['lng']))  $data['lng']  = (float) $data['lng'];
        if (isset($data['zoom'])) $data['zoom'] = (int)   $data['zoom'];
        return $data;
    }
}