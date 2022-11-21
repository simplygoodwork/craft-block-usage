<?php
/**
 * Block Usage plugin for Craft CMS 4.x
 *
 * See how Matrix and Neo blocks are being used across your sections.
 *
 * @link      https://simplygoodwork.com
 * @copyright Copyright (c) 2022 Good Work
 */

namespace simplygoodwork\blockusage;

use simplygoodwork\blockusage\services\BlockUsageService as BlockUsageServiceService;
use simplygoodwork\blockusage\variables\BlockUsageVariable;
use simplygoodwork\blockusage\behaviors\MatrixCriteriaBehavior;
use simplygoodwork\blockusage\behaviors\NeoCriteriaBehavior;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\elements\db\ElementQuery;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

/**
 * Class BlockUsage
 *
 * @author    Good Work
 * @package   BlockUsage
 * @since     1.0.0
 *
 * @property  BlockUsageServiceService $blockUsageService
 */
class BlockUsage extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var BlockUsage
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var bool
     */
    public bool $hasCpSettings = false;

    /**
     * @var bool
     */
    public bool $hasCpSection = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Craft::info(
            Craft::t(
                'block-usage',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );

        // register the variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('blockUsage', BlockUsageVariable::class);
            }
        );

        // Add the MatrixCriteriaBehavior behavior to ElementQuery objects
        Event::on(
            ElementQuery::class,
            ElementQuery::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->sender->attachBehaviors([
                    MatrixCriteriaBehavior::class,
                ]);
            }
        );

        // Add the NeoCriteriaBehavior behavior to ElementQuery objects
        Event::on(
            ElementQuery::class,
            ElementQuery::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->sender->attachBehaviors([
                    NeoCriteriaBehavior::class,
                ]);
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['block-usage/fields/<fieldId:\d+>'] = ['template' => 'block-usage/index'];
                $event->rules['block-usage/fields/<fieldId:\d+>/<blockId:\d+>'] = ['template' => 'block-usage/entries'];
            }
        );
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        return $item;
    }

    // Protected Methods
    // =========================================================================
    
}
