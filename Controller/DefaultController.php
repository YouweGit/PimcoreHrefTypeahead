<?php

namespace PimcoreHrefTypeaheadBundle\Controller;

use Pimcore\Controller\FrontendController;
use Pimcore\Tool;
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
class DefaultController extends FrontendController
{
    /**
     * @Route("/find")
     */
    public function findAction(Request $request)
    {
        return new Response('Hello world from pimcore_href_typeahead');
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
