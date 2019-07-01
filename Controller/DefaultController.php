<?php

namespace PimcoreHrefTypeaheadBundle\Controller;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Pimcore\Tool;
use PimcoreHrefTypeaheadBundle\Service\SearchBuilder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Pimcore\Logger;
use Pimcore\Model\Element;
use Pimcore\Model\Element\AbstractElement;
use Pimcore\Model\DataObject;
use PimcoreHrefTypeaheadBundle\Model\DataObject\Data\HrefTypeahead;

/**
 * Class DefaultController
 *
 * @Route("/admin/href-typeahead")
 * @package PimcoreHrefTypeaheadBundle\Controller
 */
class DefaultController extends AdminController
{
    /**
     * @Route("/find")
     */
    public function findAction(Request $request, \PimcoreHrefTypeaheadBundle\Service\SearchBuilder $searchBuilder)
    {
        $sourceId = $request->get('sourceId');
        $sourceClassName = $request->get('className');
        $valueIds = $request->get('valueIds');
        $fieldName = $request->get('fieldName');
        $source = null;
        // We know what object it is and we can get its type by id
        if ($sourceId) {
            $source = DataObject\Concrete::getById($sourceId);
        } elseif ($sourceClassName) { // We dont know what type it is by we know its class, strange but nice-path requires a specific source object
            $classFullName = "\\Pimcore\\Model\\DataObject\\$sourceClassName";
            $source = new $classFullName();
        }
        // Don`t do anything without valid source object
        if (!$source || !$fieldName) {
            return $this->adminJson(['data' => [], 'success' => false, 'total' => 0]);
        }

        /** @var HrefTypeahead $fd */
        $fd = $source->getClass()->getFieldDefinition($fieldName);
        if (!$fd || !$fd->getFieldtype() === 'hrefTypeahead' || !$fd->getObjectsAllowed() || count($fd->getClasses()) !== 1) {
            throw new \InvalidArgumentException('This function can only be called from hrefTypeahead field with one class attached');
        }
        $className = current($fd->getClasses())['classes'];
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
                $label = $this->getNicePath($fd, $valueObj, $source);
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
        $sortingSettings = \Pimcore\Admin\Helper\QueryParams::extractSortingSettings($request->request->all());
        $searchService = $searchBuilder->create()
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
                    $label = $this->getNicePath($fd, $element, $source);
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
     * @param DataObject\ClassDefinition\Data $fd
     * @param AbstractElement $element
     * @param DataObject\Concrete $source
     * @return array|mixed
     */
    private function getNicePath($fd, $element, $source)
    {
        if (!$element) {
            return null;
        }
        if (method_exists($fd, 'getPathFormatterClass')) {
            $formatterClass = $fd->getPathFormatterClass();
            if ( Tool::classExists($formatterClass)) {
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
                $result = call_user_func($formatterClass . '::formatPath', $result, $source, $target,
                    [
                        'fd' => $fd,
                        // "context" => $context
                    ]);
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
