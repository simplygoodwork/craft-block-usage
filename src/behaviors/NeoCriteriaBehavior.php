<?php

namespace simplygoodwork\blockusage\behaviors;

use Craft;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use benf\neo\elements\Block as NeoBlock;
use craft\events\CancelableEvent;

use yii\base\Behavior;

/**
 * @author    nystudio107
 * @package   SiteModule
 * @since     1.0.0
 */
class NeoCriteriaBehavior extends Behavior
{
    // Constants
    // =========================================================================

    const NO_MATCHING_NEO_CRITERIA = 'no-matching-neo-criteria';

    // Public Properties
    // =========================================================================

    /**
     * @var string the Neo field to use
     */
    public $neoFieldHandle;

    /**
     * @var array the criteria for the Neo query
     */
    public $neoCriteria;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function events()
    {
        return [
            ElementQuery::EVENT_BEFORE_PREPARE => function($event) {
                $this->applyNeoCriteriaParams($event);
            },
        ];
    }

    /**
     * Limit the ElementQuery to elements that match the passed in Neo criteria
     *
     * @param string $neoFieldHandle the handle of the Neo field to match the criteria in
     * @param array $neoCriteria the criteria for the NeoBlock query
     * @return ElementQueryInterface
     */
    public function neoCriteria(string $neoFieldHandle, array $neoCriteria): ElementQueryInterface
    {
        $this->neoFieldHandle = $neoFieldHandle;
        $this->neoCriteria = $neoCriteria;
        /* @var ElementQueryInterface $elementQuery */
        $elementQuery = $this->owner;

        return $elementQuery;
    }

    // Private Methods
    // =========================================================================

    /**
     * Apply the 'neoFieldHandle' & 'neoCriteria' params to select the ids
     * of the elements that own neo blocks that match, and then add them to the
     * id parameter of the ElementQuery
     *
     * @param CancelableEvent $event
     */
    private function applyNeoCriteriaParams(CancelableEvent $event): void
    {
        if (!$this->neoFieldHandle || empty($this->neoCriteria)) {
            return;
        }
        /* @var ElementQueryInterface $elementQuery */
        $elementQuery = $this->owner;
        // Get the id of the neo field from the handle
        $neoField = Craft::$app->getFields()->getFieldByHandle($this->neoFieldHandle);
        if ($neoField === null) {
            return;
        }
        // Set up the neo block query
        $neoQuery = NeoBlock::find();
        // Mix in any criteria for the neo block query
        Craft::configure($neoQuery, $this->neoCriteria);
        // Get the ids of the elements that contain neo blocks that match the neo block query
        $ownerIds = $neoQuery
            ->fieldId($neoField->id)
            ->select('neoblocks.primaryOwnerId')
            ->orderBy(null)
            ->distinct()
            ->column();
        // If the original query's `id` is not empty, use the intersection
        if (!empty($elementQuery->id)) {
            $originalIds = $elementQuery->id;
            if (!is_array($originalIds)) {
                $originalIds = [(int)$originalIds];
            }
            $ownerIds = array_intersect($originalIds, $ownerIds);
        }
        // Ensure the parent query returns nothing if no ids were found
        if (empty($ownerIds)) {
            $ownerIds = null;
            $elementQuery->uid = self::NO_MATCHING_NEO_CRITERIA;
        }
        // Add them to the original query that was passed in
        $elementQuery->id($ownerIds);
    }
}