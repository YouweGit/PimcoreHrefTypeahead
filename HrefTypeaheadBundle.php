<?php

namespace HrefTypeaheadBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;

class HrefTypeaheadBundle extends AbstractPimcoreBundle
{

    use PackageVersionTrait;
    
    protected function getComposerPackageName(): string
    {
        // getVersion() will use this name to read the version from
        // PackageVersions and return a normalized value
        return 'youwe/pimcore-href-typeahead';
    }
    
    public function getJsPaths()
    {
        return [
            '/bundles/pimcorehreftypeahead/js/pimcore/object/tags/hrefTypeahead.js',
            '/bundles/pimcorehreftypeahead/js/pimcore/object/classes/data/hrefTypeahead.js',
            '/bundles/pimcorehreftypeahead/js/HrefObject.js'
        ];
    }

    public function getCssPaths()
    {
        return [
            '/bundles/pimcorehreftypeahead/css/style.css'
        ];
    }
}
