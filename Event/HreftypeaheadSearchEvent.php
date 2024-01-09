<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace PimcoreHrefTypeaheadBundle\Event;

use Pimcore\Model\DataObject;
use Symfony\Component\EventDispatcher\GenericEvent;

class HreftypeaheadSearchEvent extends GenericEvent
{
    const SEARCH_EVENT = 'hreftypeahead.search';

    /** @var DataObject\Conrete */
    private $sourceObject;

    /** @var array */
    private $conditions;

    /**
     * HreftypeaheadSearchEvent constructor.
     *
     * @param DataObject\Concrete $sourceObject
     * @param string[] $conditions
     */
    public function __construct(DataObject\Concrete $sourceObject, array &$conditions)
    {
        $this->sourceObject = $sourceObject;
        $this->conditions = &$conditions;
    }

    /**
     * @return DataObject\Concrete
     */
    public function getSourceObject()
    {
        return $this->sourceObject;
    }

    /**
     * @return string[]
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    public function setConditions(array $conditions)
    {
        $this->conditions = $conditions;
    }

    public function addCondition($condition)
    {
        $this->conditions[] = $condition;
    }

}
