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
                // Check it's top level
                if ($block->topLevel) {
  
                    $children = [];
                    if ($block->childBlocks) {
                        $children = $this->_getNeoChildBlocks($field->handle, $block->childBlocks);
                    }

                    $count = Entry::find()
                        ->neoCriteria($field->handle, [
                            'type' => $block->handle,
                        ])
                        ->count();

    
                    $_blocks[] = [
                        'id' => $block->id,
                        'handle' => $block->handle,
                        'name' => $block->name,
                        'children' => $children,
                        'count' => $count,
                    ];
                }
            }
        }
  
        // Get the structure from Matrix
        elseif ($field->displayName() == 'Matrix') {
  
            foreach ($field->getBlockTypes() as $block) {

                $count = Entry::find()
                    ->matrixCriteria($field->handle, [
                        'type' => $block->handle,
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
                ->neoCriteria($field->handle, [
                    'type' => $block->handle,
                ])
                ->all();
        }
        elseif ($field->displayName() == 'Matrix') {
            $entries = Entry::find()
                ->matrixCriteria($field->handle, [
                    'type' => $block->handle,
                ])
                ->all();
        }    

        $ret['block'] = $block;
        $ret['entries'] = $entries;
                
        return $ret;
    }

    private function _getNeoChildBlocks(string $fieldHandle, array $children, $output = []): array {

        foreach( $children as $child) {
            $childBlock = \benf\neo\Plugin::$plugin->blockTypes->getByHandle($child);

            if ($childBlock->childBlocks) {
                $output = $this->_getNeoChildBlocks($childBlock->childBlocks, $output);
            }
            else {

                $count = Entry::find()
                    ->neoCriteria($fieldHandle, [
                        'type' => $childBlock->handle,
                    ])
                    ->count();
                    
                $output[$child] = [
                    'id' => $childBlock->id,
                    'handle' => $childBlock->handle,
                    'name' => $childBlock->name,
                    'count' => $count
                ];
            }
        }

        return $output;
    }
}
