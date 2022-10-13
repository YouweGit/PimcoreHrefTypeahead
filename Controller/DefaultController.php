<?php

namespace PimcoreHrefTypeaheadBundle\Controller;

use PimcoreHrefTypeaheadBundle\Service\SearchBuilder;
use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Pimcore\Bundle\AdminBundle\Helper\QueryParams;
use Pimcore\Logger;
use Pimcore\Model\Element;
use Pimcore\Model\Element\AbstractElement;
use Pimcore\Model\DataObject;
use Pimcore\Tool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DefaultController
 *
 * @Route("/admin/href-typeahead")
 * @package PimcoreHrefTypeaheadBundle\Controller
 */
class DefaultController extends AdminController
{
    /**
     * @param Request $request
     * @param SearchBuilder $searchBuilder
     * @Route("/find")
     */
    public function findAction(Request $request, SearchBuilder $searchBuilder)
    {
        $sourceId = $request->get('sourceId');
        $sourceClassName = $request->get('className');
        $valueIds = $request->get('valueIds');
        $formatterClass = $request->get('formatterClass');
        $className = $request->get('class');
        $fieldName = $request->get('fieldName'); // fieldName used to find field definition if needed

        $source = null;
        $sourceClass = null;

        // Get a sourceClass if given a sourceClassName
        if ($sourceClassName) {
            $classFullName = "\\Pimcore\\Model\\DataObject\\$sourceClassName";

            if (Tool::classExists($classFullName)) {
                $sourceClass = new $classFullName();
            }
        }

        // If we have a sourceId, grab source through the id. Otherwise, set source to be our sourceClass (which is null if it doesn't exist)
        $source = $sourceId ? DataObject\Concrete::getById($sourceId) : $sourceClass;

        // Don`t do anything without valid source object
        if (!$source) {
            return $this->adminJson(['data' => [], 'success' => false, 'total' => 0]);
        }

        // If there is a sourceClass, fieldName, and we are still missing a className, then grab the className from the allowedClasses in definition
        if ($sourceClass && $fieldName && !$className) {
            $allowedClasses = $sourceClass->getClass()->getFieldDefinition($fieldName)->getClasses();

            if (count($allowedClasses) > 0 && isset($allowedClasses[0]['classes'])) {
                $className = $allowedClasses[0]['classes'];
            }
        }

        // This is a special case when the field is loaded for the first time or they are loaded from
        if ($valueIds) {
            $valueObjs = [];
            foreach (explode_and_trim(',', $valueIds) as $valueId) {
                $valueObjs[] = DataObject\Concrete::getById($valueId);
            }

            if (!$valueObjs) {
                return $this->adminJson(['data' => [], 'success' => false, 'total' => 0]);
            }

            $elements = [];
            foreach ($valueObjs as $valueObj) {
                $label = $this->getNicePath($formatterClass, $valueObj, $source);
                $elements[] = $this->formatElement($valueObj, $label);
            }

            return $this->adminJson(['data' => $elements, 'success' => true, 'total' => count($elements)]);
        }
        // This means that we have passed the values ids
        // but the field is empty this is common when the field is empty
        // We don't need to continue looping
        elseif (!$valueIds && $request->get('valueIds')) {
            return $this->adminJson(['data' => [], 'success' => false, 'total' => 0]);
        }

        $filter = $request->get('filter') ? \Zend_Json::decode($request->get('filter')) : null;
        $considerChildTags = $request->get('considerChildTags') === 'true';
        $sortingSettings = QueryParams::extractSortingSettings($request->request->all());
        $searchService = $searchBuilder
            ->withUser($this->getAdminUser())
            ->withTypes(['object'])
            ->withSubTypes(['object'])
            ->withClassNames([$className])
            ->withQuery( $request->get('query'))
            ->withStart((int) $request->get('start'))
            ->withLimit((int) $request->get('limit'))
            ->withFields( $request->get('fields'))
            ->withFilter($filter)
            ->withSourceObject($source)
            ->withTagIds( $request->get('tagIds'))
            ->withConsiderChildTags($considerChildTags)
            ->withSortSettings($sortingSettings)
            ->build();

        $searcherList = $searchService->getListingObject();

        /** @var \Pimcore\Model\Search\Backend\Data[] $hits */
        $hits = $searcherList->load();
        $elements = [];

        foreach ($hits as $hit) {
            /** @var AbstractElement $element */
            $element = Element\Service::getElementById($hit->getId()->getType(), $hit->getId()->getId());
            if ($element->isAllowed('list')) {
                if ($element->getType() === 'object') {
                    $label = $this->getNicePath($formatterClass, $element, $source);
                } else {
                    $label = (string)$element;
                }
                $elements[] = $this->formatElement($element, $label);
            }
        }

        // only get the real total-count when the limit parameter is given otherwise use the default limit
        if ($request->get('limit')) {
            $totalMatches = $searcherList->getTotalCount();
        } else {
            $totalMatches = count($elements);
        }

        return $this->adminJson(['data' => $elements, 'success' => true, 'total' => $totalMatches]);
    }

    /**
     * @param AbstractElement $element
     * @param string $label
     * @return array
     */
    private function formatElement($element, $label)
    {
        return [
            'id' => $element->getId(),
            'fullpath' => $element->getFullPath(),
            'display' => $label,
            'type' => Element\Service::getType($element),
            'subtype' => $element->getType(),
            'nicePathKey' => Element\Service::getType($element) . '_' . $element->getId(),
        ];
    }

    /**
     * @param string $formatterClass
     * @param AbstractElement $element
     * @param DataObject\Concrete $source
     * @return array|mixed
     */
    private function getNicePath($formatterClass, $element, $source)
    {
        if (!$element) {
            return null;
        }

        if ($formatterClass) {
            if (
                Tool::classExists($formatterClass) &&
                is_a($formatterClass, DataObject\ClassDefinition\PathFormatterInterface::class, true)
            ) {
                $key = Element\Service::getType($element) . '_' . $element->getId();
                $target = [
                    $key => [
                        'dest_id' => $element->getId(),
                        'id' => $element->getId(),
                        'type' => Element\Service::getType($element),
                        'subtype' => $element->getType(),
                        'path' => $element->getPath(),
                        'index' => 0,
                        'nicePathKey' => $key,
                    ]
                ];

                $result = [];
                $result = call_user_func($formatterClass . '::formatPath', $result, $source, $target, []);
                $result = current($result);

                return $result;
            } else {
                Logger::error('Formatter Class does not exist: ' . $formatterClass);
            }
        }

        // Fall back to whatever the string representation would be
        return (string)$element;
    }
}
