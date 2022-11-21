<?php
/**
 * Block Usage plugin for Craft CMS 4.x
 *
 * Block Usage index.twig
 *
 * @author    Good Work
 * @copyright Copyright (c) 2022 Good Work
 * @link      https://simplygoodwork.com
 * @package   BlockUsage
 * @since     1.0.0
 */

namespace simplygoodwork\blockusage\variables;

use simplygoodwork\blockusage\BlockUsage;

class BlockUsageVariable
{
    public function getFields(): array
    {
        return BlockUsage::getInstance()->blockUsageService->getFields();
    }

    public function getCounts($fieldId): array
    {
        return BlockUsage::getInstance()->blockUsageService->getCounts($fieldId);
    }
 
    public function getBlockEntries($fieldId, $blockId): array
    {
        return BlockUsage::getInstance()->blockUsageService->getBlockEntries($fieldId, $blockId);
    }

}