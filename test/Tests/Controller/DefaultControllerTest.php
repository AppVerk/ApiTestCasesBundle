<?php

namespace AppVerk\ApiTestCasesBundle\test\Tests\Controller;

use AppVerk\ApiTestCasesBundle\Api\Cases\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class DefaultControllerTest extends JsonApiTestCase
{
    public function testGetRouteExists()
    {
        $this->client->request('GET', '/default/get');
        /** @var Response $response */
        $response = $this->client->getResponse();

        $this->assertResponse($response, 'getResponse', Response::HTTP_OK);
    }

    public function testPostRouteExists()
    {
        $this->client->request(
            'POST',
            '/default/post',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );
        /** @var Response $response */
        $response = $this->client->getResponse();

        $this->assertResponse($response, 'postResponse', Response::HTTP_OK);
    }
}
