<?php

namespace PimcoreHrefTypeaheadBundle\Controller;

use Pimcore\Controller\FrontendController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends FrontendController
{
    /**
     * @Route("/pimcore_href_typeahead")
     */
    public function indexAction(Request $request)
    {
        return new Response('Hello world from pimcore_href_typeahead');
    }
}
