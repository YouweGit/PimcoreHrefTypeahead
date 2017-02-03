<?php

namespace HrefTypeahead;

use Pimcore\API\Plugin as PluginLib;

class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{

    public static function install (){
        return true;
    }

    public static function uninstall (){
        return false;
    }

    public static function isInstalled ()
    {
        return true;
    }
    public static function getTranslationFile($language) {
        return '/HrefTypeahead/config/texts/en.csv';
    }
}
