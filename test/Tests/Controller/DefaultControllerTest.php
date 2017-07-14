<?php

namespace AppVerk\ApiTestCasesBundle\test\Tests\Controller;

use AppVerk\ApiTestCasesBundle\Api\Cases\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class DefaultControllerTest extends JsonApiTestCase
{
    public function testGetRouteEqxists()
    {
        $response = $this->client->get('/default/get');

        $this->assertResponse($response, null, Response::HTTP_OK);
    }
}