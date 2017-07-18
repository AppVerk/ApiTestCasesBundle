# ApiTestCasesBundle
[![Build Status](https://travis-ci.org/AppVerk/ApiTestCasesBundle.svg?branch=master)](https://travis-ci.org/AppVerk/ApiTestCasesBundle)

Test cases for boost up writing PHPUnit functional tests for API with Symfony framework.
Bundle is helping you debuging failed tests and bosting TDD process.

## Examples failed response
    
    Failure! when making the following request:
    POST: http://foo.app/app_test.php/api/security/token

    HTTP/1.1 404 Not Found
    Date: Sat, 08 Jul 2017 12:28:19 GMT
    Server: Apache
    X-Powered-By: PHP/7.0.15
    Cache-Control: no-cache, private
    Content-Length: 84
    Content-Type: application/problem+json
    {
        "detail": "Client is blocked",
        "status": 404,
        "type": "about:blank",
        "title": "Not Found"
    }

    "Client is blocked" does not match "Client is blockedx".
    @@ -1,5 +1,5 @@
     {
    -  "detail": "Client is blockedx",
    +  "detail": "Client is blocked",
       "status": 404,
       "type": "about:blank",
       "title": "Not Found"

## Usage

All you need to do is extend JsonApiTestCase in your functional controller class.

    use AppVerk\ApiTestCasesBundle\Api\Cases\JsonApiTestCase;
    use Symfony\Component\HttpFoundation\Response;
    
    class ProfileControllerTest extends JsonApiTestCase
    {
        ...
    }
    
## Testing API methods

### Test code:

    public function testMeActionSuccess()
    {
        $this->authenticateFixtureUser('profile/user.yml');
        $response = $this->client->get('/api/profile/me');

        $this->assertResponse($response, 'profile/me/success', Response::HTTP_OK);
    }
    
### Alice schema file:

    AppBundle\Entity\User:
    user1:
        username: test
        email: test@test.foo
        password: test
        
### JWT authentication method with lexik/LexikJWTAuthenticationBundle:

    protected function authenticateFixtureUser(
        string $userFixturePath,
        $expired = JwtTokenFactory::EXPIRATION_TIME
    ) {
        $this->loadFixturesFromFile($userFixturePath);

        $tokenData = [
            'username' => 'test',
            'exp'      => time() + $expired,
        ];

        $token = $this->getService('lexik_jwt_authentication.encoder')->encode($tokenData);

        self::$staticClient->setDefaultOption('headers/Authorization', 'Bearer '.$token);

        return $tokenData;
    }
    
### config file - config_test.ynl

    security:
    encoders:
        AppBundle\Entity\User: plaintext
        
## More examples

for more examples please visit https://github.com/AppVerk/BaseApi/tree/master/src/Bundle/ApiBundle/Tests
