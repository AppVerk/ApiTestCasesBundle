<?php

namespace AppVerk\ApiTestCasesBundle\test\Tests\Controller;

use AppVerk\ApiTestCasesBundle\Api\Cases\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class DefaultControllerTest extends JsonApiTestCase
{
    public function testGetRouteExists()
    {
        $response = $this->client->get('/default/get');

        $this->assertResponse($response, 'getResponse', Response::HTTP_OK);
    }

    public function testPostRouteExists()
    {
        $response = $this->client->post('/default/post', ['body' => []]);

        $this->assertResponse($response, 'postResponse', Response::HTTP_OK);
    }
}
