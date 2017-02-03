<?php
use HrefTypeahead\SearchBuilder;
use Pimcore\Logger;
use Pimcore\Model\Element;
use Pimcore\Model\Element\AbstractElement;
use Pimcore\Model\Object;
use Pimcore\Model\Object\ClassDefinition\Data\HrefTypeahead;

class HrefTypeahead_SearchController extends \Pimcore\Controller\Action\Admin
{

    /**
     * @return void
     * @throws \Exception
     */
    public function findAction()
    {
        $sourceId = $this->getParam('sourceId');
        $sourceClassName = $this->getParam('className');
        $valueIds = $this->getParam('valueIds');
        $fieldName = $this->getParam('fieldName');
        $source = null;
        // We know what object it is and we can get its type by id
        if ($sourceId) {
            $source = Object\Concrete::getById($sourceId);

        } elseif ($sourceClassName) { // We dont know what type it is by we know its class, strange but nice-path requires a specific source object
            $classListingFullName = "\\Pimcore\\Model\\Object\\$sourceClassName\\Listing";
            /** @var Object\Listing\Concrete $sourceListing */
            $sourceListing = new $classListingFullName();
            $sourceListing->setUnpublished(true);
            $sourceListing->setLimit(1);
            $source = $sourceListing->current();

        }

        // Don`t do anything without valid source object
        if (!$source || !$fieldName) {
            $this->_helper->json(['data' => [], 'success' => false, 'total' => 0]);
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
                $valueObjs[] = Object\Concrete::getById($valueId);
            }
            if (!$valueObjs) {
                $this->_helper->json(['data' => [], 'success' => false, 'total' => 0]);
            }
            $elements = [];
            foreach ($valueObjs as $valueObj) {
                $label = $this->getNicePath($fd, $valueObj, $source);
                $elements[] = $this->formatElement($valueObj, $label);

            }
            $this->_helper->json(['data' => $elements, 'success' => true, 'total' => count($elements)]);
        }

        // This means that we have passed the values ids
        // but the field is empty this is common when the field is empty
        // We don't need to continue looping
        elseif (!$valueIds && $this->hasParam('valueIds')) {
            $this->_helper->json(['data' => [], 'success' => true, 'total' => 0]);
        }


        $filter = $this->getParam('filter') ? \Zend_Json::decode($this->getParam('filter')) : null;
        $considerChildTags = $this->getParam('considerChildTags') === 'true';
        $sortingSettings = \Pimcore\Admin\Helper\QueryParams::extractSortingSettings($this->getAllParams());

        $searchService = SearchBuilder::create()
            ->withUser($this->getUser())
            ->withTypes(['object'])
            ->withSubTypes(['object'])
            ->withClassNames([$className])
            ->withQuery($this->getParam('query'))
            ->withStart((int)$this->getParam('start'))
            ->withLimit((int)$this->getParam('limit'))
            ->withFields($this->getParam('fields'))
            ->withFilter($filter)
            ->withTagIds($this->getParam('tagIds'))
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
        if ($this->getParam('limit')) {
            $totalMatches = $searcherList->getTotalCount();
        } else {
            $totalMatches = count($elements);
        }

        $this->_helper->json(['data' => $elements, 'success' => true, 'total' => $totalMatches]);

        $this->removeViewRenderer();
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
     * @param Object\ClassDefinition\Data $fd
     * @param AbstractElement $element
     * @param Object\Concrete $source
     * @return array|mixed
     */
    private function getNicePath($fd, $element, $source)
    {
        if (!$element) {
            return null;
        }
        if (method_exists($fd, 'getPathFormatterClass')) {
            $formatterClass = $fd->getPathFormatterClass();
            if (Pimcore\Tool::classExists($formatterClass)) {
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
