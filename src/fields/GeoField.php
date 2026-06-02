<?php


/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace sup\craftgeo\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\db\ElementQueryInterface;
use craft\events\CancelableEvent;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use sup\craftgeo\Geo;
use sup\craftgeo\services\GeoService;
use sup\craftgeo\web\GeoAsset;
use yii\db\ExpressionInterface;
use yii\db\Schema;
use craft\base\PreviewableFieldInterface;
use craft\base\Event;
use craft\elements\db\ElementQuery;
use craft\gql\GqlEntityRegistry;
use craft\gql\types\QueryArgument;
use sup\craftgeo\integrations\graphql\types\GeoType;
use GraphQL\Type\Definition\Type;
use craft\helpers\Json;
use sup\craftgeo\models\EmbedMap;
use sup\craftgeo\models\StaticMap;
use sup\craftgeo\records\GeoRecord;


class GeoField extends Field implements PreviewableFieldInterface
{
	public float $lat = -34.925;
	public float $lng = 138.60;
	public int $zoom = 9;
	public float $minZoom = 3;
	public float $maxZoom = 18;
	public int $mapHeigth = 500;
    public ?string $country = null;
    public ?string $countryCode = null;
    public ?string $full_address = null;
    public ?string $address1 = null;
    public ?string $suburb = null;
    public ?int $postcode = null;
    public ?string $geoJson = null;
    public bool $hideSearch = false;
    public bool $hideAddress = false;
    public bool $hideMap = false;
    public bool $showLatLng = false;
	private static mixed $searchParams = null;


    public function afterElementSave(ElementInterface $element, bool $isNew): void
    {
        if ($element->getIsDraft() || $element->getIsRevision() || !$element->getIsCanonical()) {
            parent::afterElementSave($element, $isNew);
            return;
        }

        $value = $element->getFieldValue($this->handle);

        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (!is_array($value)) {
            parent::afterElementSave($element, $isNew);
            return;
        }

        $lat = isset($value['lat']) && is_numeric($value['lat']) ? (float) $value['lat'] : null;
        $lng = isset($value['lng']) && is_numeric($value['lng']) ? (float) $value['lng'] : null;

        Craft::$app->db->createCommand()->upsert(
            '{{%supgeo}}',
            [
                'ownerId'     => $element->id,
                'ownerSiteId' => $element->siteId,
                'fieldId'     => $this->id,
                'lat'         => $lat,
                'lng'         => $lng,
            ],
            [
                'lat' => $lat,
                'lng' => $lng,
            ]
        )->execute();

        parent::afterElementSave($element, $isNew);
    }

    public function afterElementDelete(ElementInterface $element): void
    {
        GeoRecord::deleteAll([
            'ownerId' => $element->id,
            'fieldId' => $this->id,
        ]);

        parent::afterElementDelete($element);
    }

    public function init(): void
	{
		Event::on(
			ElementQuery::class,
			ElementQuery::EVENT_AFTER_PREPARE,
			[$this, 'afterPrepareElementQuery'],
		);

		parent::init();
	}

    public static function queryCondition(array $instances, mixed $value, array &$params): array|string|ExpressionInterface|false|null
	{
		if (empty($instances) || empty($value))
			return null;

		self::$searchParams = [
			'field' => $instances[0],
			'value' => $value,
		];

		return null;
	}

    public function beforeElementSave(ElementInterface $element, bool $isNew): bool
    {
        $value = $element->getFieldValue($this->handle);

        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (!is_array($value)) {
            return parent::beforeElementSave($element, $isNew);
        }
       
        if (isset($value['lat']) || isset($value['lng'])) {
            $error = $this->validateLatLngValues($value);
            if ($error !== null) {
                $element->addError($this->handle, Craft::t('geo', $error));
                return false;
            }
        }
        if (empty($value['geoJson'])) {
            return parent::beforeElementSave($element, $isNew);
        }
        $geoJson = $value['geoJson'];
        // Must be valid JSON
        $decoded = json_decode($geoJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $element->addError(
                $this->handle,
                Craft::t('geo', 'GeoJSON is not valid JSON: {error}', [
                    'error' => json_last_error_msg(),
                ])
            );
            return false;
        }

        // Must have a type property
        if (empty($decoded['type'])) {
            $element->addError(
                $this->handle,
                Craft::t('geo', 'GeoJSON must have a "type" property.')
            );
            return false;
        }

        // Must be a valid GeoJSON type
        $validTypes = [
            'Point',
            'MultiPoint',
            'LineString',
            'MultiLineString',
            'Polygon',
            'MultiPolygon',
            'GeometryCollection',
            'Feature',
            'FeatureCollection',
        ];

        if (!in_array($decoded['type'], $validTypes, true)) {
            $element->addError(
                $this->handle,
                Craft::t('geo', 'GeoJSON type "{type}" is not valid. Must be one of: {valid}', [
                    'type'  => $decoded['type'],
                    'valid' => implode(', ', $validTypes),
                ])
            );
            return false;
        }

        $error = match ($decoded['type']) {
            'Point'              => $this->validateGeoJsonPoint($decoded),
            'MultiPoint',
            'LineString'         => $this->validateGeoJsonCoordinateArray($decoded, 2),
            'MultiLineString',
            'Polygon'            => $this->validateGeoJsonCoordinateArray($decoded, 3),
            'MultiPolygon'       => $this->validateGeoJsonCoordinateArray($decoded, 4),
            'GeometryCollection' => $this->validateGeoJsonGeometryCollection($decoded),
            'Feature'            => $this->validateGeoJsonFeature($decoded),
            'FeatureCollection'  => $this->validateGeoJsonFeatureCollection($decoded),
            default              => null,
        };

        if ($error !== null) {
            $element->addError($this->handle, Craft::t('geo', $error));
            return false;
        }

        return parent::beforeElementSave($element, $isNew);
    }

    private function  validateLatLngValues(array $value): ?string{
        $hasLat = isset($value['lat']) && $value['lat'] !== '';
        $hasLng = isset($value['lng']) && $value['lng'] !== '';
        if ($hasLat && !$hasLng) {
         return 'Longitude is required when Latitude is set.';
        }

        if ($hasLng && !$hasLat) {
         return 'Latitude is required when Longitude is set.';
        }

        if($hasLat){
            if (!is_numeric($value['lat'])) {
                return 'Latitude must be a number.';
            }
        }

        if($hasLng){
            if (!is_numeric($value['lng'])) {
              return 'Longitude must be a number.';
            }
        }
        return null;
    }

    private function validateGeoJsonPoint(array $data): ?string
    {
        if (!isset($data['coordinates']) || !is_array($data['coordinates'])) {
            return 'GeoJSON Point must have a "coordinates" array.';
        }

        if (count($data['coordinates']) < 2) {
            return 'GeoJSON Point coordinates must have at least [longitude, latitude].';
        }

        [$lng, $lat] = $data['coordinates'];

        if (!is_numeric($lng) || $lng < -180 || $lng > 180) {
            return 'GeoJSON Point longitude must be between -180 and 180.';
        }

        if (!is_numeric($lat) || $lat < -90 || $lat > 90) {
            return 'GeoJSON Point latitude must be between -90 and 90.';
        }

        return null;
    }

    private function validateGeoJsonCoordinateArray(array $data, int $depth): ?string
    {
        if (!isset($data['coordinates']) || !is_array($data['coordinates'])) {
            return 'GeoJSON ' . $data['type'] . ' must have a "coordinates" array.';
        }

        if (empty($data['coordinates'])) {
            return 'GeoJSON ' . $data['type'] . ' coordinates must not be empty.';
        }

        // Recursively check we reach numeric pairs at the correct depth
        $error = $this->validateCoordinateDepth($data['coordinates'], $depth, $data['type']);

        return $error;
    }

    private function validateCoordinateDepth(array $coords, int $depth, string $type): ?string
    {
        if ($depth === 1) {
            // Should be a [lng, lat] pair
            if (count($coords) < 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
                return "GeoJSON {$type} contains an invalid coordinate pair.";
            }
            return null;
        }

        foreach ($coords as $item) {
            if (!is_array($item)) {
                return "GeoJSON {$type} has malformed coordinate nesting.";
            }
            $error = $this->validateCoordinateDepth($item, $depth - 1, $type);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private function validateGeoJsonGeometryCollection(array $data): ?string
    {
        if (!isset($data['geometries']) || !is_array($data['geometries'])) {
            return 'GeoJSON GeometryCollection must have a "geometries" array.';
        }

        foreach ($data['geometries'] as $geometry) {
            if (!isset($geometry['type'])) {
                return 'Each geometry in a GeometryCollection must have a "type".';
            }
        }

        return null;
    }

    private function validateGeoJsonFeature(array $data): ?string
    {
        if (!array_key_exists('geometry', $data)) {
            return 'GeoJSON Feature must have a "geometry" property.';
        }


        // geometry can be null (valid per spec) or an object with a type
        if ($data['geometry'] !== null && empty($data['geometry']['type'])) {
            return 'GeoJSON Feature geometry must have a "type".';
        }

        return null;
    }

    private function validateGeoJsonFeatureCollection(array $data): ?string
    {
        if (!isset($data['features']) || !is_array($data['features'])) {
            return 'GeoJSON FeatureCollection must have a "features" array.';
        }

        foreach ($data['features'] as $i => $feature) {
            if (!isset($feature['type']) || $feature['type'] !== 'Feature') {
                return "GeoJSON FeatureCollection item #{$i} must be a Feature type.";
            }

            $error = $this->validateGeoJsonFeature($feature);
            if ($error !== null) {
                return "GeoJSON FeatureCollection item #{$i}: {$error}";
            }
        }

        return null;
    }

    public static function displayName(): string
    {
        return Craft::t('geo', 'Geo Maps');
    }

    public static function icon(): string
    {
        return 'i-cursor';
    }

    public static function phpType(): string
    {
        return 'mixed';
    }

    public static function dbType(): array|string|null
    {
        // Replace with the appropriate data type this field will store in the database,
        // or `null` if the field is managing its own data storage.
        return  Schema::TYPE_STRING;
    }


    public function getContentGqlType(): array|Type
    {
        return GeoType::getType();
    }

    public function getContentGqlMutationArgumentType(): array|\GraphQL\Type\Definition\Type
    {
        return [
            'name'         => $this->handle,
            'type'         =>  Type::string(),
            'description'  => 'JSON string or object with lat, lng, zoom, address fields',
        ];
    }

    public function getContentGqlQueryArgumentType(): array|\GraphQL\Type\Definition\Type
    {
        return [
            'name' => $this->handle,
            'type' => QueryArgument::getType(),
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            // ...
        ]);
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [
				'lat',
				'double',
				'min' => -90,
				'max' => 90
			],
			[
				'lng',
				'double',
				'min' => -180,
				'max' => 180
			],
			[
                'zoom',
                'integer',
                'min' => 5,
                'max' => 18
			]
        ]);
    }

    public function renderMapField($isInput = false):string{
        $view = Craft::$app->getView();
        $handle  = $this->handle ?? 'geo-preview';
        $locale = Craft::$app->locale->id;
        $id = Craft::$app->getView()->formatInputId($handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);
        
        $view->registerAssetBundle(GeoAsset::class);

        $settings = Geo::getInstance()->getSettings();
        $mapToken =  $settings->getMapToken() ?? null;
        $geoToken =  $settings->getGeoToken() ?? null;
        if($mapToken){
          $view->registerJsFile('https://maps.googleapis.com/maps/api/js?key='.$mapToken.'&loading=async&libraries=places');
        }
        $view->registerCssFile('https://unpkg.com/leaflet@latest/dist/leaflet.css');
		$view->registerCssFile('https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.css');
        $lat  = (float) $this->lat;
        $lng  = (float) $this->lng;
        $zoom  = (int) $this->zoom;

        if($lat  == 0 || $lat != null){
            $lat =  -34.925;
        }

        if($lng  == 0 || $lat != null){
            $lng = 138.60;
        }

       return Craft::$app->getView()->renderTemplate('geo/components/map',[
            'name' => $this->handle,
            'namespacedId' => $namespacedId,
            'mapdata' => [
                'lat'=> $lat,
                'lng'=> $lng,
                'zoom'=> $zoom,
                'isInput'=> $isInput,
                'geoToken'=>$geoToken
            ]
       ]);
    }

	public function beforeSave (bool $isNew): bool
	{
		$this->lat  = (float) $this->lat;
		$this->lng  = (float) $this->lng;
		$this->zoom = (int) $this->zoom;

		if ($this->country === '*')
			$this->country = null;

		return parent::beforeSave($isNew);
	}

    public function getSettingsHtml(): ?string
    {
         $mapField = $this->renderMapField();
         $countries = array_merge([
                '*' => 'All Countries',
         ], GeoService::$countries);
         $handle  = $this->handle ?? 'geo-preview';
        $id = Craft::$app->getView()->formatInputId($handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);

         return Craft::$app->getView()->renderTemplate('geo/fields/GeoSettings', [
            'field' => $this,
            'map'=> $mapField,
            'id'=>  $id,
            'namespacedId'=>  $namespacedId,
            'countries'=> $countries 
        ]);
    }

    private function createStaticMapModel(?float $lat, ?float $lng, mixed $zoom = null): StaticMap
    {
        $map = new StaticMap();
        $map->center = [$lat, $lng];
        $map->zoom = $zoom ?? 14;
        return $map;
    }



    private function createEmbedMapModel(array $options = [])
    {
        $lat = 
        $map = (new EmbedMap())->embed(array_merge([
            'center' => $options['center'],
            'zoom'   => $options['zoom'] ?? 14,
        ], $options));
        return $map;
    }


    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if (is_string($value) && !empty($value)) {
            $value = json_decode($value, true) ?? $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        $tmpVal =  array_merge($value, array_filter([
            'lat'  => isset($value['lat']) && is_numeric($value['lat']) ? (float) $value['lat']  : null,
            'lng'  => isset($value['lng']) && is_numeric($value['lng']) ? (float) $value['lng']  : null,
            'zoom' => isset($value['zoom']) ? (int)   $value['zoom'] : null,
            'postcode' => isset($value['postcode']) ? (int) $value['postcode'] : null,
        ], fn($v) => $v !== null));

        return [
            ...$tmpVal,
            'map' => $this->createStaticMapModel($tmpVal['lat'], $tmpVal['lng'], $tmpVal['zoom'] ?? null),
            'embed'=> $this->createEmbedMapModel([
               'center' => ['lat' => $tmpVal['lat'], 'lng' =>$tmpVal['lng']],
               'zoom'=> $tmpVal['zoom'] ?? 14
            ])
        ];
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $view = Craft::$app->getView();
        $locale = Craft::$app->locale->id;
        $id = Craft::$app->getView()->formatInputId($this->handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);

        $lat  = (float) $this->lat;
        $lng  = (float) $this->lng;
        $zoom  = (int) $this->zoom;

        $settings = Geo::getInstance()->getSettings(); // Global settings
        $mapToken =  $settings->getMapToken() ?? null;
        $geoToken =  $settings->getGeoToken() ?? null;
       
        $view->registerAssetBundle(GeoAsset::class);
        if($mapToken){
          $view->registerJsFile('https://maps.googleapis.com/maps/api/js?key='.  $mapToken . '&libraries=places');
        }
        $view->registerCssFile('https://unpkg.com/leaflet@latest/dist/leaflet.css');
		$view->registerCssFile('https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.css');

        return Craft::$app->getView()->renderTemplate('geo/_input',[
                'name' => $this->handle,
                'namespacedId' => $namespacedId,
                'value'=> $value,
                'id' => $id,
                'elementId' => $element->id,
                'mapdata' => [
                    'lat'=> $lat,
                    'lng'=> $lng,
                    'zoom'=> $zoom,
                    'geoToken'=> $geoToken,
                ],
                'global_settings'=> $settings,
                'settings'=> $this->getSettings()
        ]);
    }

    public function getElementValidationRules(): array
    {
        return [];
    }

    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        return StringHelper::toString($value, ' ');
    }


    public function afterPrepareElementQuery(CancelableEvent $event): void{
        if (!self::$searchParams) return;
        $query = $event->sender;
    
        Geo::getInstance()->map->modifyElementsQuery(
            $query,
            self::$searchParams['value'],
            self::$searchParams['field'],
        );
        self::$searchParams = null;

    }


    public function getElementConditionRuleType(): array|string|null
    {
        return null;
    }

 
}
