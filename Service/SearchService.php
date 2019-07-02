<?php


namespace PimcoreHrefTypeaheadBundle\Service;

use Pimcore\Db;
use Pimcore\Model\Element;
use Pimcore\Model\DataObject;
use Pimcore\Model\Search\Backend;
use Pimcore\Model\Search\Backend\Data;
use Pimcore\Model\User;
use PimcoreHrefTypeaheadBundle\Event\HreftypeaheadSearchEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class SearchService
 * Builds a listing object based on default search functionality
 */
class SearchService
{

    /** @var array */
    private $types;
    /** @var array */
    private $subTypes;
    /** @var User */
    private $user;
    /** @var array */
    private $classNames;
    /** @var string */
    private $query;
    /** @var int */
    private $start;
    /** @var int */
    private $limit;
    /** @var array */
    private $fields;
    /** @var array */
    private $filter;
    /** @var DataObject\Concrete */
    private $sourceObject;
    /** @var array */
    private $tagIds;
    /** @var bool */
    private $considerChildTags;
    private $sortSettings;

    /* @var EventDispatcherInterface */
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher) {
        $this->dispatcher = $dispatcher;
    }

    public function fromBuilder(SearchBuilder $searchBuilder)
    {
        if ($searchBuilder->getUser() === null || !$searchBuilder->getUser() instanceof User) {
            throw new \InvalidArgumentException('Missing user or invalid, please review what we passed to SearchBuilder class');
        }
        if (!is_array($searchBuilder->getSortSettings()) || count($searchBuilder->getSortSettings()) === 0) {
            throw new \InvalidArgumentException('Missing user or invalid');
        }

        $this->db = Db::get();


        $this->types = $searchBuilder->getTypes();
        $this->subTypes = $searchBuilder->getSubTypes();
        $this->user = $searchBuilder->getUser();
        $this->classNames = $searchBuilder->getClassNames();
        $this->query = $searchBuilder->getQuery();
        $this->start = $searchBuilder->getStart();
        $this->limit = $searchBuilder->getLimit();
        $this->fields = $searchBuilder->getFields();
        $this->filter = $searchBuilder->getFilter();
        $this->tagIds = $searchBuilder->getTagIds();
        $this->considerChildTags = $searchBuilder->isConsiderChildTags();
        $this->sortSettings = $searchBuilder->getSortSettings();
        $this->sourceObject = $searchBuilder->getSourceObject();

        // Force subtype to obj, var, folder when all objects are allowed
        /** @noinspection NotOptimalIfConditionsInspection */
        if (current($this->types) === 'object' && is_array($this->classNames) && empty($this->classNames[0])) {
            $this->subTypes = ['object', 'variant', 'folder'];
        }
    }


    /**
     * @return Backend\Data\Listing
     * @throws \Exception
     */
    public function getListingObject()
    {
        $this->sanitizeQuery();

        $searcherList = new Data\Listing();
        $conditionParts = [];
        $forbiddenConditions = [];

        //exclude forbidden assets
        $forbiddenConditions = $this->addForbiddenAssetCondition($forbiddenConditions);

        //exclude forbidden documents
        $forbiddenConditions = $this->addForbiddenDocumentConditions($forbiddenConditions);

        //exclude forbidden objects
        $forbiddenConditions = $this->addForbiddenObjectCondition($forbiddenConditions);

        if ($forbiddenConditions) {
            $conditionParts[] = '(' . implode(' AND ', $forbiddenConditions) . ')';
        }


        if ($this->query !== '') {
            $conditionParts = $this->addQueryCondition($conditionParts);
        }


        //For objects - handling of bricks
        $bricks = [];
        if ($this->fields) {
            foreach ($this->fields as $f) {
                $parts = explode('~', $f);
                if (strpos($f, '~') !== 0 && count($parts) > 1) {
                    $bricks[ $parts[0] ] = $parts[0];
                }
            }
        }
        $className = current($this->classNames);
        // filtering for objects
        if ($this->filter && $className) {
            $class = DataObject\ClassDefinition::getByName($className);

            // add Localized Fields filtering
            $params = $this->filter;
            $unLocalizedFieldsFilters = [];
            $localizedFieldsFilters = [];

            foreach ($params as $paramConditionObject) {
                //this loop divides filter parameters to localized and un-localized groups
                $definitionExists = in_array('o_' . $paramConditionObject['property'], DataObject\Service::getSystemFields(), true)
                    || $class->getFieldDefinition($paramConditionObject['property']);
                //TODO: for sure, we can add additional condition like getLocalizedFieldDefinition()->getFieldDefinition(...
                if ($definitionExists) {
                    $unLocalizedFieldsFilters[] = $paramConditionObject;
                } else {
                    $localizedFieldsFilters[] = $paramConditionObject;
                }
            }

            //get filter condition only when filters array is not empty

            //string statements for divided filters
            $conditionFilters = count($unLocalizedFieldsFilters)
                ? DataObject\Service::getFilterCondition(json_encode($unLocalizedFieldsFilters), $class)
                : null;
            $localizedConditionFilters = count($localizedFieldsFilters)
                ? DataObject\Service::getFilterCondition(json_encode($localizedFieldsFilters), $class)
                : null;

            $join = '';
            foreach ($bricks as $ob) {
                $join .= ' LEFT JOIN object_brick_query_' . $ob . '_' . $class->getId();

                $join .= ' `' . $ob . '`';
                $join .= ' ON `' . $ob . '`.o_id = `object_' . $class->getId() . '`.o_id';
            }

            if (null !== $conditionFilters) {
                //add condition query for non localised fields
                $conditionParts[] = '( id IN (SELECT `object_' . $class->getId() . '`.o_id FROM object_' . $class->getId()
                    . $join . ' WHERE ' . $conditionFilters . ') )';
            }

            if (null !== $localizedConditionFilters) {
                //add condition query for localised fields
                $conditionParts[] = '( id IN (SELECT `object_localized_data_' . $class->getId()
                    . '`.ooo_id FROM object_localized_data_' . $class->getId() . $join . ' WHERE '
                    . $localizedConditionFilters . ' GROUP BY ooo_id ' . ') )';
            }
        }

        if (is_array($this->types) && !empty($this->types[0])) {
            foreach ($this->types as $type) {
                $conditionTypeParts[] = $this->db->quote($type);
            }
            if (in_array('folder', $this->subTypes, true)) {
                $conditionTypeParts[] = $this->db->quote('folder');
            }
            /** @noinspection PhpUndefinedVariableInspection */
            $conditionParts[] = '( maintype IN (' . implode(',', $conditionTypeParts) . ') )';
        }

        if (is_array($this->subTypes) && !empty($this->subTypes[0])) {
            foreach ($this->subTypes as $subtype) {
                $conditionSubtypeParts[] = $this->db->quote($subtype);
            }
            /** @noinspection PhpUndefinedVariableInspection */
            $conditionParts[] = '( type IN (' . implode(',', $conditionSubtypeParts) . ') )';
        }

        if (is_array($this->classNames) && !empty($this->classNames[0])) {
            if (in_array('folder', $this->subTypes, true)) {
                $classNames[] = 'folder';
            }
            foreach ($this->classNames as $className) {
                $conditionClassNameParts[] = $this->db->quote($className);
            }
            /** @noinspection PhpUndefinedVariableInspection */
            $conditionParts[] = '( subtype IN (' . implode(',', $conditionClassNameParts) . ') )';
        }

        //filtering for tags
        $conditionParts = $this->appendTagConditions($conditionParts);

        $this->dispatcher->dispatch('hreftypeahead.search', new HreftypeaheadSearchEvent($this->sourceObject, $conditionParts));

        if (count($conditionParts) > 0) {
            $condition = implode(' AND ', $conditionParts);
            $searcherList->setCondition($condition);
        }

        $this->addLimits($searcherList);
        $this->addSorting($searcherList);

        return $searcherList;
    }

    /**
     * Appends tag conditions in main condition parts from getListingObject
     * @param array $conditionParts
     * @return array
     */
    private function appendTagConditions($conditionParts)
    {
        if ($this->tagIds) {
            foreach ($this->tagIds as $tagId) {
                foreach ($this->types as $type) {
                    if ($this->considerChildTags) {
                        /** @var Element\Tag $tag */
                        $tag = Element\Tag::getById($tagId);
                        if ($tag) {
                            $tagPath = $tag->getFullIdPath();
                            $conditionParts[] = 'id IN (SELECT cId FROM tags_assignment INNER JOIN tags ON tags.id = tags_assignment.tagid WHERE ctype = ' . $this->db->quote($type) . ' AND (id = ' . (int)$tagId . ' OR idPath LIKE ' . $this->db->quote($tagPath . '%') . '))';
                        }
                    } else {
                        $conditionParts[] = 'id IN (SELECT cId FROM tags_assignment WHERE ctype = ' . $this->db->quote($type) . ' AND tagid = ' . (int)$tagId . ')';
                    }
                }
            }

            return $conditionParts;
        }

        return $conditionParts;
    }

    /**
     * Appends forbidden asset conditions from main condition parts from getListingObject
     * @param array $forbiddenConditions
     * @return array
     */
    private function addForbiddenAssetCondition($forbiddenConditions)
    {
        if (in_array('asset', $this->types, true)) {
            if (!$this->user->isAllowed('assets')) {
                $forbiddenConditions[] = " `type` != 'asset' ";

                return $forbiddenConditions;
            } else {
                $forbiddenAssetPaths = Element\Service::findForbiddenPaths('asset', $this->user);
                if (count($forbiddenAssetPaths) > 0) {
                    /** @noinspection CallableInLoopTerminationConditionInspection */
                    for ($i = 0; $i < count($forbiddenAssetPaths); $i++) {
                        $forbiddenAssetPaths[ $i ] = " (maintype = 'asset' AND fullpath not like " . $this->db->quote($forbiddenAssetPaths[ $i ] . '%') . ')';
                    }
                    $forbiddenConditions[] = implode(' AND ', $forbiddenAssetPaths);

                    return $forbiddenConditions;
                }

                return $forbiddenConditions;
            }
        }

        return $forbiddenConditions;
    }

    /**
     * Appends forbidden document conditions from main condition parts from getListingObject
     * @param array $forbiddenConditions
     * @return array
     */
    private function addForbiddenDocumentConditions($forbiddenConditions)
    {
        if (in_array('document', $this->types, true)) {
            if (!$this->user->isAllowed('documents')) {
                $forbiddenConditions[] = " `type` != 'document' ";

                return $forbiddenConditions;
            } else {
                $forbiddenDocumentPaths = Element\Service::findForbiddenPaths('document', $this->user);
                if (count($forbiddenDocumentPaths) > 0) {
                    /** @noinspection CallableInLoopTerminationConditionInspection */
                    for ($i = 0; $i < count($forbiddenDocumentPaths); $i++) {
                        $forbiddenDocumentPaths[ $i ] = " (maintype = 'document' AND fullpath not like " . $this->db->quote($forbiddenDocumentPaths[ $i ] . '%') . ')';
                    }
                    $forbiddenConditions[] = implode(' AND ', $forbiddenDocumentPaths);

                    return $forbiddenConditions;
                }

                return $forbiddenConditions;
            }
        }

        return $forbiddenConditions;
    }

    /**
     * Appends forbidden object conditions from main condition parts from getListingObject
     * @param array $forbiddenConditions
     * @return array
     */
    private function addForbiddenObjectCondition($forbiddenConditions)
    {
        if (in_array('object', $this->types, true)) {
            if (!$this->user->isAllowed('objects')) {
                $forbiddenConditions[] = " `type` != 'object' ";

                return $forbiddenConditions;
            } else {
                $forbiddenObjectPaths = Element\Service::findForbiddenPaths('object', $this->user);
                if (count($forbiddenObjectPaths) > 0) {
                    /** @noinspection CallableInLoopTerminationConditionInspection */
                    for ($i = 0; $i < count($forbiddenObjectPaths); $i++) {
                        $forbiddenObjectPaths[ $i ] = " (maintype = 'object' AND fullpath not like " . $this->db->quote($forbiddenObjectPaths[ $i ] . '%') . ')';
                    }
                    $forbiddenConditions[] = implode(' AND ', $forbiddenObjectPaths);

                    return $forbiddenConditions;
                }

                return $forbiddenConditions;
            }
        }

        return $forbiddenConditions;
    }

    /**
     * @param array $conditionParts
     * @return array
     */
    private function addQueryCondition($conditionParts)
    {
        $queryCondition = '( MATCH (`data`,`properties`) AGAINST (' . $this->db->quote($this->query) . ' IN BOOLEAN MODE) )';

        // the following should be done with an exact-search now "ID", because the Element-ID is now in the fulltext index
        // if the query is numeric the user might want to search by id
        //if(is_numeric($query)) {
        //$queryCondition = "(" . $queryCondition . " OR id = " . $this->db->quote($query) ." )";
        //}

        $conditionParts[] = $queryCondition;

        return $conditionParts;
    }

    /**
     * @param Backend\Data\Listing $searcherList
     */
    private function addLimits($searcherList)
    {
        $offset = $this->start ?: 0;
        $limit = $this->limit ?: 50;

        $searcherList->setOffset($offset);
        $searcherList->setLimit($limit);
    }

    private function sanitizeQuery()
    {
        if ($this->query === '*') {
            $this->query = '';
        }

        $this->query = str_replace('%', '*', $this->query);
        $this->query = preg_replace("@([^ ])\-@", "$1 ", $this->query);
        if(strlen($this->query) > 0){
            $this->query = '*' . $this->query . '*';
        }
    }

    /**
     * @param Backend\Data\Listing $searcherList
     */
    private function addSorting($searcherList)
    {
        if ($this->sortSettings['orderKey']) {
            // we need a special mapping for classname as this is stored in subtype column
            $sortMapping = [
                'classname' => 'subtype'
            ];

            $sort = $this->sortSettings['orderKey'];
            if (array_key_exists($this->sortSettings['orderKey'], $sortMapping)) {
                $sort = $sortMapping[ $this->sortSettings['orderKey'] ];
            }
            $searcherList->setOrderKey($sort);
        }
        if ($this->sortSettings['order']) {
            $searcherList->setOrder($this->sortSettings['order']);
        }
    }
}
