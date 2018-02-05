<?php

namespace Pimcore\Model\Object\ClassDefinition\Data;

use Pimcore\Model;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\Element;
use Pimcore\Model\Object;

class HrefTypeahead extends Href
{
    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = "hrefTypeahead";


    /**
     * @see Object\ClassDefinition\Data::getDataForEditmode
     * @param Asset|Document|Object\AbstractObject $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return array
     */
    public function getDataForEditmode($data, $object = null, $params = [])
    {
        if ($data instanceof Element\ElementInterface) {
            $r = [
                "id"      => $data->getId(),
                "path"    => $data->getRealFullPath(),
                "subtype" => $data->getType(),
                "type"    => Element\Service::getElementType($data),
            ];

            if ($data instanceof Object\Concrete) {
                $r['display'] = (string)$data;
            }

            return $r;
        }

        return;
    }

    public function getDataForGrid($data, $object = null, $params = [])
    {
        if ($data instanceof Element\ElementInterface) {
            return (string)$data;
        }
    }
}
