<?php

namespace AppVerk\ApiTestCasesBundle\test\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class DefaultController extends Controller
{

    public function getAction()
    {
        return new JsonResponse(['data' => 'get']);
    }

    public function postAction()
    {
        return new JsonResponse(['data' => 'post']);
    }
}
