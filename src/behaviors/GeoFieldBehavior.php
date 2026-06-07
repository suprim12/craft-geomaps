<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace sup\craftgeo\behaviors;

use Craft;
use craft\elements\db\ElementQuery;
use craft\events\CancelableEvent;
use sup\craftgeo\fields\GeoField;
use sup\craftgeo\records\GeoRecord;
use sup\craftgeo\services\GeoService;
use yii\base\Behavior;
use yii\base\Event;

class GeoFieldBehavior extends Behavior
{
    private array $_pendingSearches = [];

    public function attach($owner): void
    {
        parent::attach($owner);

        Event::on(
            ElementQuery::class,
            ElementQuery::EVENT_AFTER_PREPARE,
            [$this, 'handleAfterPrepare']
        );
    }

    public function __call($name, $params)
    {
        $field = Craft::$app->fields->getFieldByHandle($name);

        if ($field && $field instanceof GeoField) {
            if (!empty($params[0]) && is_array($params[0])) {
                $this->_pendingSearches[] = [
                    'field'  => $field,
                    'params' => $params[0],
                ];
                return $this->owner;
            }
        }

        return parent::__call($name, $params);
    }

    public function hasMethod($name): bool
    {
        $field = Craft::$app->fields->getFieldByHandle($name);
        if ($field && $field instanceof GeoField) {
            return true;
        }
        return parent::hasMethod($name);
    }

    public function handleAfterPrepare(CancelableEvent $event): void
    {
        if ($event->sender !== $this->owner) {
            return;
        }

        if (empty($this->_pendingSearches)) {
            return;
        }

        foreach ($this->_pendingSearches as $search) {
            $this->applyGeoSearch(
                $event->sender,
                $search['field'],
                $search['params']
            );
        }

        $this->_pendingSearches = [];
    }


    private function applyGeoSearch(ElementQuery $query, $field, array $params): void
    {
        if (!$query->subQuery) {
            Craft::error('GeoFieldBehavior: subQuery is null, cannot apply geo search', 'geo');
            return;
        }

        $location   = $params['location']   ?? null;
        $coordinate = $params['coordinate'] ?? null; 
        $radius     = (float) ($params['radius'] ?? 50);
        $unit       = GeoService::normalizeDistance($params['unit'] ?? 'km');
        $country    = $params['country'] ?? null;

        $coords = null;

        if ($coordinate && isset($coordinate['lat'], $coordinate['lng'])) {
            $coords = [
                'lat' => (float) $coordinate['lat'],
                'lng' => (float) $coordinate['lng'],
            ];
        } elseif ($location) {
            $coords = GeoService::resolveLocation($location, $country);
        }

        if (!$coords) {
              Craft::warning(
                "GeoFieldBehavior: geocoding failed for location \"{$location}\"",
                'geo'
            );
            $query->orderBy = [];
            $query->subQuery->andWhere('1=0');
            return;
        }

        $lat   = $coords['lat'];
        $lng   = $coords['lng'];
        $table = GeoRecord::TABLE_NAME_CLEAN;
        $alias = $table . '_' . $field->handle;

        $query->subQuery->leftJoin(
            GeoRecord::TABLE_NAME . ' ' . $alias,
            implode(' AND ', [
                "[[elements.id]] = [[{$alias}.ownerId]]",
                "[[elements_sites.siteId]] = [[{$alias}.ownerSiteId]]",
                "[[{$alias}.fieldId]] = " . $field->id,
            ])
        );

        $distance = $unit === 'km' ? '111.045' : '69.0';

        $distanceExpr = str_replace(["\r", "\n", "\t"], '', "(
            {$distance} *
            DEGREES(
                ACOS(
                    LEAST(1.0,
                        COS(RADIANS({$lat})) *
                        COS(RADIANS([[{$alias}.lat]])) *
                        COS(RADIANS({$lng}) - RADIANS([[{$alias}.lng]])) +
                        SIN(RADIANS({$lat})) *
                        SIN(RADIANS([[{$alias}.lat]]))
                    )
                )
            )
        )");

        $restrict = [
            'and',
            "[[{$alias}.lat]] >= {$lat} - ({$radius} / {$distance})",
            "[[{$alias}.lat]] <= {$lat} + ({$radius} / {$distance})",
            "[[{$alias}.lng]] >= {$lng} - ({$radius} / ({$distance} * COS(RADIANS({$lat}))))",
            "[[{$alias}.lng]] <= {$lng} + ({$radius} / ({$distance} * COS(RADIANS({$lat}))))",
        ];

        $query->subQuery
            ->addSelect("{$distanceExpr} as [[distance]]")
            ->andWhere($restrict)
            ->andWhere(['not', ["[[{$alias}.lat]]" => null]])
            ->andHaving('[[distance]] <= ' . $radius);

        $query->orderBy('[[distance]] ASC');
    }
}