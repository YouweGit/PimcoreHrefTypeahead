<?php

namespace PimcoreHrefTypeaheadBundle\Controller;

use Pimcore\Controller\FrontendController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DefaultController
 *
 * @Route("/admin/href-typeahead")
 * @package PimcoreHrefTypeaheadBundle\Controller
 */
class DefaultController extends FrontendController
{
    /**
     * @Route("/search")
     */
    public function indexAction(Request $request)
    {
        return new Response('Hello world from pimcore_href_typeahead');
    }
}
