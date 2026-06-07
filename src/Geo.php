<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace sup\craftgeo;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\db\ElementQuery;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Fields;
use craft\feedme\services\Fields as FeedMeFields;
use sup\craftgeo\fields\GeoField;
use sup\craftgeo\models\Settings;
use yii\base\Event;
use craft\web\UrlManager;
use sup\craftgeo\integrations\feedme\fields\GeoFeedMeField as FieldsGeoFeedMeField;
use craft\events\RegisterGqlTypesEvent;
use craft\services\Gql;
use craft\web\twig\variables\CraftVariable;
use sup\craftgeo\behaviors\GeoFieldBehavior;
use sup\craftgeo\integrations\graphql\types\GeoCoordsInput;
use sup\craftgeo\integrations\graphql\types\GeoQueryArgument;
use sup\craftgeo\integrations\graphql\types\GeoType;
use sup\craftgeo\migrations\Install;
use sup\craftgeo\services\MapService;
use sup\craftgeo\services\ZoneService;
use sup\craftgeo\variables\GeoVariable as VariablesGeoVariable;
use sup\craftgeo\web\GeoVariable;

class Geo extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        //  Set Components
        $this->setComponents([
            'maps' => MapService::class,
            'zones'=> ZoneService::class
        ]); 

        $this->attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
    }

    public function createInstallMigration(): ?\craft\db\Migration
    {
        return new \sup\craftgeo\migrations\Install();
    }

    public function createUninstallMigration(): ?\craft\db\Migration
    {
        return new \sup\craftgeo\migrations\Uninstall();
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    public function getSettings(): Settings
    {
        $settings = parent::getSettings();
        $config = Craft::$app->config->getConfigFromFile('geo');

        if (!empty($config)) {
            Craft::configure($settings, $config);
        }

        return $settings;
    }


    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('geo/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'config'=> Craft::$app->config->getConfigFromFile('geo'),
        ]);
    }


   public function onRegisterCPUrlRules (RegisterUrlRulesEvent $event)
    {
    }

    public function onRegisterFeedMeFields (\craft\feedme\events\RegisterFeedMeFieldsEvent $event)
    {
      $event->fields[] = FieldsGeoFeedMeField::class;
    }

    private function registerGqlTypes(): void
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            function (RegisterGqlTypesEvent $event) {
                $event->types[] = GeoType::class;
                $event->types[] = GeoQueryArgument::class;
                $event->types[] = GeoCoordsInput::class;
            }
        );
    }

    public function onRegisterVariable (Event $event)
	{
		/** @var CraftVariable $variable */
		$variable = $event->sender;
		$variable->set('maps', GeoVariable::class);
        $variable->set('geomaps', VariablesGeoVariable::class);
	}


    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = GeoField::class;
        });


        Event::on(
        ElementQuery::class,
        ElementQuery::EVENT_DEFINE_BEHAVIORS,
        function (DefineBehaviorsEvent $event) {
            $event->behaviors['geoField'] = GeoFieldBehavior::class;
        }
    );

		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			[$this, 'onRegisterCPUrlRules']
		);


        Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			[$this, 'onRegisterVariable']
		);


        if (class_exists(\craft\feedme\Plugin::class))
		{
			Event::on(
				\craft\feedme\services\Fields::class,
				\craft\feedme\services\Fields::EVENT_REGISTER_FEED_ME_FIELDS,
				[$this, 'onRegisterFeedMeFields']
			);
		}
        // add gql intergations
        $this->registerGqlTypes();
    }
}
