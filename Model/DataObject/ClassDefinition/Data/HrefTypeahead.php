<?php

namespace PimcoreHrefTypeaheadBundle\Model\DataObject\ClassDefinition\Data;

use Pimcore\Model;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\Element;

class HrefTypeahead extends Model\DataObject\ClassDefinition\Data\Href
{
    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = "hrefTypeahead";

    /**
     * Allowed document types
     *
     * @var boolean
     */
    public $showTrigger;

    /**
     * @return bool
     */
    public function getShowTrigger()
    {
        return $this->showTrigger;
    }

    /**
     * @param bool $show_trigger
     *
     * @return $this
     */
    public function setShowTrigger($showTrigger)
    {
        $this->showTrigger = $showTrigger;

        return $this;
    }


    /**
     * @see Model\DataObject\ClassDefinition\Data::getDataForEditmode
     * @param Asset|Document|Model\DataObject\AbstractObject $data
     * @param null|Model\DataObject\AbstractObject $object
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

            if ($data instanceof Model\DataObject\Concrete) {
                $r['display'] = (string)$data;
            }

            return $r;
        }

        return [];
    }

    public function getDataForGrid($data, $object = null, $params = [])
    {
        if ($data instanceof Element\ElementInterface) {
            return (string)$data;
        }
    }
}
