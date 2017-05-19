<?php

namespace Pimcore\Model\Object\ClassDefinition\Data;

use Pimcore\Model\Element;

class HrefTypeahead extends Href
{
    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = "hrefTypeahead";

    public function getDataForGrid($data, $object = null, $params = [])
    {
        if ($data instanceof Element\ElementInterface) {
            return (string)$data;
        }
    }
}
