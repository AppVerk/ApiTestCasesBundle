<?php

namespace AppVerk\ApiTestCasesBundle\Api\Cases;

use Coduo\PHPMatcher\Matcher;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Client;
use Fidry\AliceDataFixtures\Loader\PurgerLoader;
use GuzzleHttp\Message\AbstractMessage;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Subscriber\History;
use Nelmio\Alice\Loader\SimpleFilesLoader;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

abstract class ApiTestCase extends WebTestCase
{

    /**
     * @var Client
     */
    protected static $staticClient;

    /**
     * @var History
     */
    private static $history;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $dataFixturesPath;

    /**
     * @var string
     */
    protected $expectedResponsesPath;

    /**
     * @var ConsoleOutput
     */
    private $output;

    /**
     * @var FormatterHelper
     */
    private $formatterHelper;

    /** @var PurgerLoader */
    private $loader;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
    }

    public static function setUpBeforeClass() : void
    {
        self::$staticClient = static::createClient();

        self::bootKernel();
    }

    protected function setUp() : void
    {
        $this->client = self::$staticClient;

        $this->setUpDatabase();
    }

    public function setUpDatabase()
    {
        if (isset($_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) && $_SERVER['IS_DOCTRINE_ORM_SUPPORTED']) {
            $this->purgeDatabase();
        }
    }

    protected function getService($id)
    {
        return self::$kernel->getContainer()
            ->get($id);
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getService('doctrine.orm.entity_manager');
    }

    protected function getManager($name = null)
    {
        return $this->getDoctrine()->getManager($name);
    }

    /**
     * @return RegistryInterface
     */
    private function getDoctrine()
    {
        return $this->getService('doctrine');
    }

    private function purgeDatabase()
    {
        /** @var EntityManagerInterface $manager */
        foreach ($this->getDoctrine()->getManagers() as $manager) {
            $purger = new ORMPurger($manager);
            $purger->purge();
        }
    }

    /**
     * Clean up Kernel usage in this test.
     */
    protected function tearDown(): void
    {
        // purposefully not calling parent class, which shuts down the kernel
    }

    protected function onNotSuccessfulTest(Throwable $e): void
    {
        if (self::$history && $lastResponse = self::$history->getLastResponse()) {
            $this->printDebug('');
            $this->printDebug('<error>Failure!</error> when making the following request:');
            $this->printLastRequestUrl();
            $this->printDebug('');

            $this->debugResponse($lastResponse);
        }

        throw $e;
    }

    /**
     * Print a message out - useful for debugging
     *
     * @param $string
     */
    protected function printDebug($string)
    {
        if ($this->output === null) {
            $this->output = new ConsoleOutput();
        }

        $this->output->writeln($string);
    }

    protected function printLastRequestUrl()
    {
        $lastRequest = self::$history->getLastRequest();

        if ($lastRequest) {
            $this->printDebug(
                sprintf('<comment>%s</comment>: <info>%s</info>', $lastRequest->getMethod(), $lastRequest->getUrl())
            );
        } else {
            $this->printDebug('No request was made.');
        }
    }

    protected function debugResponse(Response $response)
    {
        $this->printDebug(AbstractMessage::getStartLineAndHeaders($response));
        $body = (string)$response->getContent();

        $contentType = $response->getHeader('Content-Type');
        if ($contentType == 'application/json' || strpos($contentType, '+json') !== false) {
            $data = json_decode($body);
            if ($data === null) {
                // invalid JSON!
                $this->printDebug($body);
            } else {
                // valid JSON, print it pretty
                $this->printDebug(json_encode($data, JSON_PRETTY_PRINT));
            }
        } else {
            // the response is HTML - see if we should print all of it or some of it
            $isValidHtml = strpos($body, '</body>') !== false;

            if ($isValidHtml) {
                $this->printDebug('');
                $crawler = new Crawler($body);

                // very specific to Symfony's error page
                $isError = $crawler->filter('#traces-0')->count() > 0
                    || strpos($body, 'looks like something went wrong') !== false;
                if ($isError) {
                    $this->printDebug('There was an Error!!!!');
                    $this->printDebug('');
                } else {
                    $this->printDebug('HTML Summary (h1 and h2):');
                }

                // finds the h1 and h2 tags and prints them only
                foreach ($crawler->filter('h1, h2')->extract(['_text']) as $header) {
                    // avoid these meaningless headers
                    if (strpos($header, 'Stack Trace') !== false) {
                        continue;
                    }
                    if (strpos($header, 'Logs') !== false) {
                        continue;
                    }

                    // remove line breaks so the message looks nice
                    $header = str_replace("\n", ' ', trim($header));
                    // trim any excess whitespace "foo   bar" => "foo bar"
                    $header = preg_replace('/(\s)+/', ' ', $header);

                    if ($isError) {
                        $this->printErrorBlock($header);
                    } else {
                        $this->printDebug($header);
                    }
                }

                /*
                 * When using the test environment, the profiler is not active
                 * for performance. To help debug, turn it on temporarily in
                 * the config_test.yml file (framework.profiler.collect)
                 */
                $profilerUrl = $response->getHeader('X-Debug-Token-Link');
                if ($profilerUrl) {
                    $fullProfilerUrl = $response->getHeader('Host').$profilerUrl;
                    $this->printDebug('');
                    $this->printDebug(
                        sprintf(
                            'Profiler URL: <comment>%s</comment>',
                            $fullProfilerUrl
                        )
                    );
                }

                // an extra line for spacing
                $this->printDebug('');
            } else {
                $this->printDebug($body);
            }
        }
    }

    /**
     * Print a debugging message out in a big red block
     *
     * @param $string
     */
    protected function printErrorBlock($string)
    {
        if ($this->formatterHelper === null) {
            $this->formatterHelper = new FormatterHelper();
        }
        $output = $this->formatterHelper->formatBlock($string, 'bg=red;fg=white', true);

        $this->printDebug($output);
    }

    /**
     * @param string $source
     *
     * @return array
     */
    protected function loadFixturesFromDirectory($source = '')
    {
        $source = $this->getFixtureRealPath($source);
        $this->assertSourceExists($source);
        $finder = new Finder();
        $finder->files()->name('*.yml')->in($source);
        if (0 === $finder->count()) {
            throw new \RuntimeException(sprintf('There is no files to load in folder %s', $source));
        }
        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $this->getFixtureLoader()->load($files);
    }

    /**
     * @param string $source
     *
     * @return string
     */
    private function getFixtureRealPath($source)
    {
        $baseDirectory = $this->getFixturesFolder();

        return PathBuilder::build($baseDirectory, $source);
    }

    /**
     * @return string
     */
    private function getFixturesFolder()
    {
        if (null === $this->dataFixturesPath) {
            $this->dataFixturesPath = isset($_SERVER['FIXTURES_DIR']) ?
                PathBuilder::build($this->getRootDir(), $_SERVER['FIXTURES_DIR']) :
                PathBuilder::build($this->getCalledClassFolder(), '..', 'DataFixtures', 'ORM');
        }

        return $this->dataFixturesPath;
    }

    /**
     * @return string
     */
    protected function getRootDir()
    {
        return $this->getService('kernel')->getRootDir();
    }

    /**
     * @return string
     */
    protected function getCalledClassFolder()
    {
        $calledClass = get_called_class();
        $calledClassFolder = dirname((new \ReflectionClass($calledClass))->getFileName());
        $this->assertSourceExists($calledClassFolder);

        return $calledClassFolder;
    }

    /**
     * @param string $source
     */
    private function assertSourceExists($source)
    {
        if (!file_exists($source)) {
            throw new \RuntimeException(sprintf('File %s does not exist', $source));
        }
    }

    /**
     * @return PurgerLoader
     */
    protected function getFixtureLoader()
    {
        if (!$this->loader) {
            $this->loader = self::$container->get('fidry_alice_data_fixtures.loader.doctrine');
        }

        return $this->loader;
    }

    /**
     * @param string $source
     *
     * @return array
     */
    protected function loadFixturesFromFile($source)
    {
        $source = $this->getFixtureRealPath($source);
        $this->assertSourceExists($source);

        return $this->getFixtureLoader()->load([$source]);
    }

    /**
     * @param Response $response
     * @param string $contentType
     */
    protected function assertHeader(Response $response, $contentType)
    {
        self::assertTrue(
            ($response->headers->get('Content-Type') == $contentType),
            $response->headers->get('Content-Type')
        );
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
        if ($actualResponse != 'null') {
            $result = $matcher->match($actualResponse, $expectedResponse);
            $error = $matcher->getError();
        } else {
            $result = false;
            $error = '| INVALID JSON FORMAT |';
        }
        if (!$result) {
            if ($actualResponse != 'null') {
                $actualResponseArray = explode(PHP_EOL, $actualResponse);
                foreach ($actualResponseArray as $k => $row) {
                    $actualResponseArray[$k] = str_replace('    "', '  "', $row);
                }
            } else {
                $actualResponseArray = [];
            }
            $diff = new \Diff(explode(PHP_EOL, $expectedResponse), $actualResponseArray);
            self::fail($error.PHP_EOL.$diff->render(new \Diff_Renderer_Text_Unified()));
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
     * @return Matcher
     */
    abstract protected function buildMatcher();

    /**
     * @param $content
     *
     * @return string
     */
    protected function prettifyJson($content)
    {
        return json_encode(json_decode($content), JSON_PRETTY_PRINT);
    }
}
