<?php

namespace AppVerk\ApiTestCasesBundle\Api\Cases;

use GuzzleHttp\Message\ResponseInterface;

abstract class JsonApiTestCase extends ApiTestCase
{
    /**
     * Asserts that response has JSON content.
     * If filename is set, asserts that response content matches the one in given file.
     * If statusCode is set, asserts that response has given status code.
     *
     * @param ResponseInterface $response
     * @param string|null $filename
     * @param int|null $statusCode
     */
    protected function assertResponse(ResponseInterface $response, $filename, $statusCode = 200)
    {
        $this->assertResponseCode($response, $statusCode);
        $this->assertJsonHeader($response, $statusCode);
        $this->assertJsonResponseContent($response, $filename);
    }

    /**
     * @param ResponseInterface $response
     * @param int $statusCode
     */
    protected function assertResponseCode(ResponseInterface $response, $statusCode)
    {
        self::assertEquals($statusCode, $response->getStatusCode());
    }

    /**
     * @param ResponseInterface $response
     */
    private function assertJsonHeader(ResponseInterface $response, $statusCode)
    {
        $contentType = 'application/json';
        if ($statusCode >= 400) {
            $contentType = 'application/problem+json';
        }
        self::assertHeader($response, $contentType);
    }

    /**
     * Asserts that response has JSON content matching the one given in file.
     *
     * @param ResponseInterface $response
     * @param string $filename
     *
     * @throws \Exception
     */
    private function assertJsonResponseContent(ResponseInterface $response, $filename)
    {
        parent::assertResponseContent($this->prettifyJson($response->getBody()), $filename, 'json');
    }

    /**
     * @param string $actualResponse
     * @param string $filename
     * @param string $mimeType
     */
    protected function assertResponseContent($actualResponse, $filename, $mimeType)
    {
        $responseSource = $this->getExpectedResponsesFolder();
        $actualResponse = trim($actualResponse);
        $expectedResponse = trim(
            file_get_contents(PathBuilder::build($responseSource, sprintf('%s.%s', $filename, $mimeType)))
        );
        $matcher = $this->buildMatcher();
        $result = $matcher->match($actualResponse, $expectedResponse);
        if (!$result) {
            $diff = new \Diff(explode(PHP_EOL, $expectedResponse), explode(PHP_EOL, $actualResponse), []);
            self::fail($matcher->getError().PHP_EOL.$diff->render(new \Diff_Renderer_Text_Unified()));
        }
    }

    /**
     * @return string
     */
    private function getExpectedResponsesFolder()
    {
        if (null === $this->expectedResponsesPath) {
            $this->expectedResponsesPath = isset($_SERVER['EXPECTED_RESPONSE_DIR']) ?
                PathBuilder::build($this->getRootDir(), $_SERVER['EXPECTED_RESPONSE_DIR']) :
                PathBuilder::build($this->getCalledClassFolder(), '..', 'Responses', 'Expected');
        }

        return $this->expectedResponsesPath;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildMatcher()
    {
        return MatcherFactory::buildJsonMatcher();
    }

}