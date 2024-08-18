<?php
/**
 * Block Usage plugin for Craft CMS 5.x
 *
 * See how Matrix and Neo blocks are being used across your sections.
 *
 * @link      https://simplygoodwork.com
 * @copyright Copyright (c) 2022 Good Work
 */

namespace simplygoodwork\blockusage\services;

use craft\base\Field;
use craft\db\Query;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\conditions\entries\EntryCondition;
use craft\fields\conditions\EmptyFieldConditionRule;
use craft\helpers\ElementHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\EntryType;
use craft\models\Section;
use craft\services\ElementSources;
use Exception;
use simplygoodwork\blockusage\BlockUsage;
use craft\models\FieldLayout;
use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\base\Chippable;
use craft\base\Colorable;
use craft\base\CpEditable;
use craft\base\ElementContainerFieldInterface;
use craft\base\ElementInterface;
use craft\base\FieldLayoutProviderInterface;
use craft\base\Iconic;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\db\Expression;
use function array_filter;

/**
 * @author    Good Work
 * @package   BlockUsage
 * @since     1.0.0
 */
class BlockUsageService extends Component
{
    // Public Methods
    // =========================================================================

    /*
     * @return mixed
     */
    public function getFields()
    {
        $allFields = Craft::$app->fields->getAllFields();
        $blockFields = array_filter($allFields, fn($i) => in_array($i->displayName(), ['Neo', 'Matrix']));

        $_fields = [];

        foreach ($blockFields as $field) {
            $_fields[] = [
                'id' => $field->id,
                'type' => $field->displayName(),
                'name' => $field->name,
            ];
        }

        return $_fields;
    }

    public function getCounts(int $fieldId)
    {
        $field = Craft::$app->fields->getFieldById($fieldId);

        // Get the structure from Neo
        if ($field->displayName() == 'Neo') {

            // Loop over top level Block Types
            /** @var $block BlockType */
            foreach ($field->getBlockTypes() as $block) {

                $count = Entry::find()
                    ->neoCriteria($field->handle, [
                        'type' => $block->handle,
                    ])
                    ->count();

                $_blocks[] = [
                    'id' => $block->id,
                    'handle' => $block->handle,
                    'name' => $block->name,
                    'color' => $block->color->value ?? null,
                    'count' => $count,
                    'notTopLevel' => !$block->topLevel,
                ];
            }
        }

        // Get the structure from Matrix
        elseif ($field->displayName() == 'Matrix') {

            foreach($field->getEntryTypes() as $entryType)
            {
                $entries = Entry::find()
                    ->site(Craft::$app->request->get('site') ?? Craft::$app->sites->primarySite->handle)
                    ->typeId($entryType->id)
                    ->collect();

                $topLevelEntries = $entries->map(function($entry){
                    try {
                        $owner = $entry->getOwner();
                        while($owner->getOwner()) {
                            $owner = $owner->getOwner();
                        }

                        return $owner;
                    } catch(Exception $e) {
                        return $entry;
                    }
                })->unique('canonicalId');

                $labelHtml = $this->_getEntryTypeLabel($entryType);
                $_blocks[] = [
                    'id' => $entryType->id,
                    'handle' => $entryType->handle,
                    'color' => $entryType->color->value ?? null,
                    'name' => $labelHtml,
                    'count' => $topLevelEntries->count(),
                ];
            }
        }

        return $_blocks;
    }

    public function getBlockEntries(int $fieldId, int $blockId)
    {
        $field = Craft::$app->fields->getFieldById($fieldId);
        $block = current(array_filter($field->getBlockTypes(), fn($b) => $b->id == $blockId));

        if ($field->displayName() == 'Neo') {
            $entries = Entry::find()
                ->neoCriteria($field->handle, [
                    'type' => $block->handle,
                ])
                ->all();
        }
        elseif ($field->displayName() == 'Matrix') {
            $entries = $block->getUsages();
        }

        $ret['block'] = $block;
        $ret['entries'] = $entries;

        return $ret;
    }

    public function getEntryTypeEntries(int $fieldId, int $entryTypeId)
    {
        $entryType = Craft::$app->getEntries()->getEntryTypeById($entryTypeId) ?? Craft::$app->getEntries()->getEntryTypeById($fieldId);

        if(!$entryType) {
            return [
                'block' => [
                    'name' => '',
                ],
                'entries' => []
            ];
        }

        $entries = Entry::find()
            ->site(Craft::$app->request->get('site') ?? Craft::$app->sites->primarySite->handle)
            ->typeId($entryType->id)
            ->status(null)
            ->collect();

        $topLevelEntries = $entries
            ->filter(function($entry) use ($fieldId){
                return $entry->fieldId === $fieldId;
            })
            ->map(function($entry){
                try {
                    $owner = $entry->getOwner();
                    if($owner) {
                        while($owner->getOwner()) {
                            $owner = $owner->getOwner();
                        }
                    }


                    return $owner ?? $entry;
                } catch(Exception $e) {
                    return $entry;
                }
            })
            ->unique('canonicalId');

        $labelHtml = $this->_getEntryTypeLabel($entryType);
        return [
            'block' => [
                'name' => $labelHtml,
            ],
            'entries' => $topLevelEntries->all()
        ];
    }

    public function getEntryTypes(int $fieldId)
    {
        $field = Craft::$app->fields->getFieldById($fieldId);
        return $this->getFieldUsagesInLayouts($field);
    }

    public function getFieldUsagesInLayouts(Field $field): array
    {
        $layouts = Craft::$app->getFields()->findFieldUsages($field);
        if (empty($layouts)) {
//            return Html::tag('i', Craft::t('app', 'No usages'));
            return [];
        }

        /** @var FieldLayout[][] $layoutsByType */
        $layoutsByType = ArrayHelper::index($layouts,
            fn(FieldLayout $layout) => $layout->uid,
            [fn(FieldLayout $layout) => $layout->type ?? '__UNKNOWN__'],
        );
        /** @var FieldLayout[] $unknownLayouts */
        $unknownLayouts = ArrayHelper::remove($layoutsByType, '__UNKNOWN__');
        /** @var FieldLayout[] $layoutsWithProviders */
        $layoutsWithProviders = [];

        // re-fetch as many of these as we can from the element types,
        // so they have a chance to supply the layout providers
        foreach ($layoutsByType as $type => &$typeLayouts) {
            /** @var string|ElementInterface $type */
            /** @phpstan-ignore-next-line */
            foreach ($type::fieldLayouts(null) as $layout) {
                if (isset($typeLayouts[$layout->uid]) && $layout->provider instanceof Chippable) {
                    $layoutsWithProviders[] = $layout;
                    unset($typeLayouts[$layout->uid]);
                }
            }
        }
        unset($typeLayouts);

        $labels = [];
        $items = array_map(function(FieldLayout $layout) use (&$labels) {
            /** @var FieldLayoutProviderInterface&Chippable $provider */
            $provider = $layout->provider;
            $label = $labels[] = $provider->getUiLabel();
            $url = $provider instanceof CpEditable ? $provider->getCpEditUrl() : null;

            return [
                'id' => $provider->id,
                'label' => $label,
                'url' => $url,
                'type' => get_class($provider),
                'entryType' => $provider,
            ];
        }, $layoutsWithProviders);

        // sort by label
        array_multisort($labels, SORT_ASC, $items);

        foreach ($layoutsByType as $type => $typeLayouts) {
            // any remaining layouts for this type?
            if (!empty($typeLayouts)) {
                /** @var string|ElementInterface $type */
                $items[] = Craft::t('app', '{total, number} {type} {total, plural, =1{field layout} other{field layouts}}', [
                    'total' => count($typeLayouts),
                    'type' => $type::lowerDisplayName(),
                ]);
            }
        }

        if (!empty($unknownLayouts)) {
            $items[] = Craft::t('app', '{total, number} {type} {total, plural, =1{field layout} other{field layouts}}', [
                'total' => count($unknownLayouts),
                'type' => Craft::t('app', 'unknown'),
            ]);
        }

        return $items;
    }

    public function getEntryTypeUsages(EntryType $entryType): array
    {
        $usages = $entryType->findUsages();
        if (empty($usages)) {
            return [];
        }

        $labels = [];
        $items = array_map(function(Section|ElementContainerFieldInterface $usage) use (&$labels) {
            if ($usage instanceof Section) {
                $label = Craft::t('site', $usage->name);
                $url = $usage->getCpEditUrl();
            } else {
                $label = Craft::t('site', $usage->name);
                $url = UrlHelper::cpUrl("settings/fields/edit/$usage->id");
            }
            return [
                'id' => $usage->id,
                'label' => $label,
                'url' => $url,
                'type' => get_class($usage),
                'field' => $usage,
            ];
        }, $entryType->findUsages());

        return $items;
    }

    public function getElementQuery()
    {
        /** @var string|ElementInterface $elementType */
        /** @phpstan-var class-string<ElementInterface>|ElementInterface $elementType */

        $query = Entry::find();
        $conditionsService = Craft::$app->getConditions();

        $source = ElementHelper::findSource("craft\\elements\\Entry", "custom:f90cf240-39fc-4205-b9f9-3f327169a184", "index");

        // Does the source specify any criteria attributes?
        if ($source['type'] === ElementSources::TYPE_CUSTOM) {
            /** @var ElementConditionInterface $sourceCondition */
            $sourceCondition = $conditionsService->createCondition($source['condition']);

            $sourceCondition->modifyQuery($query);
        }



        return $query->getRawSql();

    }

    private function _getEntryTypeLabel(EntryType $entryType)
    {
        $icon = $entryType->icon ?? null;
        $labelHtml = Html::beginTag('span', [
            'class' => ['flex', 'flex-nowrap', 'gap-s'],
        ]);
        if ($icon) {
            $labelHtml .= Html::tag('div', Cp::iconSvg($icon), [
                'class' => array_filter([
                    'cp-icon',
                    'small',
                    $entryType->getColor()?->value ?? '',
                ]),
            ]);
        }
        $labelHtml .= Html::tag('span', Html::encode($entryType->name)) .
            Html::endTag('span');

        return $labelHtml;
    }
}
