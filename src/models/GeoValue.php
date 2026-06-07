<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\models;

use craft\base\Model;

/**
 * GeoValue is the object returned by GeoField::normalizeValue().
 *
 * Twig usage:
 *
 *   {{ entry.location.embed({ id: 'map' }) }}
 *   {{ entry.location.map.img() }}
 *   {{ entry.location.lat }}, {{ entry.location.lng }}
 */
class GeoValue extends Model
{
    // -------------------------------------------------------------------------
    // Raw field data
    // -------------------------------------------------------------------------

    public ?float  $lat          = null;
    public ?float  $lng          = null;
    public ?int    $zoom         = null;
    public ?string $full_address = null;
    public ?string $address1     = null;
    public ?string $suburb       = null;
    public ?int    $postcode     = null;
    public ?string $country      = null;
    public ?string $countryCode  = null;
    public ?string $geoJson      = null;

    // -------------------------------------------------------------------------
    // Lazy-initialised map models
    // -------------------------------------------------------------------------

    /** Cached StaticMap instance — built on first access. */
    private ?StaticMap $_staticMap = null;

    /**
     * Returns the StaticMap model for this value.
     *
     * Usage:
     *   <img src="{{ entry.location.map.img() }}">
     *   <img srcset="{{ entry.location.map.imgSrcSet() }}">
     */
    public function getMap(): ?StaticMap
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }

        if ($this->_staticMap === null) {
            $this->_staticMap         = new StaticMap();
            $this->_staticMap->center = ['lat' => $this->lat, 'lng' => $this->lng];
            $this->_staticMap->zoom   = $this->zoom ?? 14;
        }

        return $this->_staticMap;
    }

    /**
     * Renders an interactive embed div and registers the required JS/CSS.
     * Called only when the template actually outputs the embed, so assets
     * are never registered on pages that don't use it.
     *
     * Usage:
     *   {{ entry.location.embed({ id: 'map', markers: [{}] }) }}
     *   {{ entry.location.embed()|attr({ class: 'map' }) }}
     *
     * The `center` and `zoom` options are pre-filled from the field value
     * but can be overridden via $options if needed.
     */
    public function embed(array $options = []): \Twig\Markup
    {
        $model = new EmbedMap();

        // Field value provides the defaults; caller options take precedence.
        $merged = array_merge([
            'center' => ['lat' => $this->lat, 'lng' => $this->lng],
            'zoom'   => $this->zoom ?? 14,
        ], $options);

        return $model->embed($merged);
    }

    // -------------------------------------------------------------------------
    // Craft property accessor support
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Serialisation — used by Feed Me, GraphQL mutations, toArray(), etc.
    // -------------------------------------------------------------------------

    /**
     * Defines which properties are included when Yii serialises this model
     * (toArray(), JSON encoding, REST responses). Excludes the lazy map models
     * so they are never accidentally serialised.
     */
    public function fields(): array
    {
        return [
            'lat',
            'lng',
            'zoom',
            'full_address',
            'address1',
            'suburb',
            'postcode',
            'country',
            'countryCode',
            'geoJson',
        ];
    }
}