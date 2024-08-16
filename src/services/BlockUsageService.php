<?php
/**
 * Block Usage plugin for Craft CMS 4.x
 *
 * See how Matrix and Neo blocks are being used across your sections.
 *
 * @link      https://simplygoodwork.com
 * @copyright Copyright (c) 2022 Good Work
 */

namespace simplygoodwork\blockusage\services;

use simplygoodwork\blockusage\BlockUsage;

use Craft;
use craft\base\Component;
use craft\elements\Entry;

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
                    ->site(Craft::$app->request->get('site') ?? Craft::$app->sites->primarySite->handle)
                    ->neoCriteria($field->handle, [
                        'type' => $block->handle,
                        'site' => Craft::$app->request->get('site') ?? Craft::$app->sites->primarySite->handle,
                    ])
                    ->count();
    
                $_blocks[] = [
                    'id' => $block->id,
                    'handle' => $block->handle,
                    'name' => $block->name,
                    'count' => $count,
                    'notTopLevel' => !$block->topLevel,
                ];
            }
        }
  
        // Get the structure from Matrix
        elseif ($field->displayName() == 'Matrix') {
  
            foreach ($field->getBlockTypes() as $block) {

                $count = Entry::find()
                    ->site(Craft::$app->request->get('site') ?? Craft::$app->sites->primarySite->handle)
                    ->matrixCriteria($field->handle, [
                        'type' => $block->handle,
                        'site' => Craft::$app->request->get('site') ?? Craft::$app->sites->primarySite->handle,
                    ])
                    ->count();

                $_blocks[] = [
                    'id' => $block->id,
                    'name' => $block->name,
                    'count' => $count,
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
                ->site(Craft::$app->request->get('site') ?? Craft::$app->sites->primarySite->handle)
                ->neoCriteria($field->handle, [
                    'type' => $block->handle,
                    'site' => Craft::$app->request->get('site') ?? Craft::$app->sites->primarySite->handle,
                ])
                ->all();
        }
        elseif ($field->displayName() == 'Matrix') {
            $entries = Entry::find()
                ->site(Craft::$app->request->get('site') ?? Craft::$app->sites->primarySite->handle)
                ->matrixCriteria($field->handle, [
                    'type' => $block->handle,
                    'site' => Craft::$app->request->get('site') ?? Craft::$app->sites->primarySite->handle,
                ])
                ->all();
        }    

        $ret['block'] = $block;
        $ret['entries'] = $entries;
                
        return $ret;
    }
}
