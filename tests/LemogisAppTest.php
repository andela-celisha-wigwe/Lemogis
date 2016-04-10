<?php

use Slim\Http\Environment;
use Elchroy\Lemogis\LemogisApp as App;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Elchroy\Lemogis\Models\LemogisModel as Emoji;
use Elchroy\Lemogis\Models\LemogisUser as User;
use Firebase\JWT\JWT;

class LemogisAppTest extends \PHPUnit_Framework_TestCase {

    private $app;
    private $response;

    public function setUp()
    {
        $_SESSION = array();
        $this->app = new App();
        $this->response = new \Slim\Http\Response();
    }

    public function testFirstTest()
    {
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/hello/roy']
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response, []);

        $result = ((string) $response->getBody());
        $this->assertSame('Hello, roy', $result);
    }

    public function testGetAll()
    {
        Emoji::truncate();
        $this->populateEmoji();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/emogis']
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response, []);

        $result = ((string) $response->getBody());
        $expected = '[{"id":1,"name":"smile","chars":"s","keywords":"smile","category":"expressions","date_created":"2016-03-12 17:04:18","date_modified":"2016-03-12 17:04:30","created_by":"roy"},{"id":2,"name":"smiley","chars":"sly","keywords":"smilely","category":"expressions","date_created":"2016-02-12 17:04:20","date_modified":"2016-02-12 17:05:18","created_by":"roy"}]';
        $this->assertSame($expected, $result);
    }

    public function testGetOneEmoji()
    {
        Emoji::truncate();
        $this->populateEmoji();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/emogis/2']
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response, []);

        $result = ((string) $response->getBody());
        $expected = '{"message":"OK","data":{"id":2,"name":"smiley","chars":"sly","keywords":"smilely","category":"expressions","date_created":"2016-02-12 17:04:20","date_modified":"2016-02-12 17:05:18","created_by":"roy"}}';
        $this->assertSame($expected, $result);
    }

    private function populateEmoji()
    {
        Emoji::create([
            'name' => 'smile',
            'chars' => 's',
            'keywords' => 'smile',
            'category' => 'expressions',
            'date_created' => '2016-03-12 17:04:18',
            'date_modified' => '2016-03-12 17:04:30',
            'created_by' => 'roy',
        ]);
        Emoji::create([
            'name' => 'smiley',
            'chars' => 'sly',
            'keywords' => 'smilely',
            'category' => 'expressions',
            'date_created' => '2016-02-12 17:04:20',
            'date_modified' => '2016-02-12 17:05:18',
            'created_by' => 'roy',
        ]);
    }

    private function populateUser()
    {
        User::create([
            'username' => 'roy',
            'password' => password_hash('ceejay', PASSWORD_DEFAULT),
            'tokenID' => NULL,
        ]);
        User::create([
            'username' => 'royz',
            'password' => 'ceejay',
            'tokenID' => NULL,
        ]);
    }

    public function testPostToCreateOneEmoji()
    {
        $token = $this->createToken('roy');
        // First delete all the entried inside the datatabase;
        Emoji::truncate();
        User::truncate();
        $this->populateUser();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/emogis',
            'HTTP_AUTHORIZATION' => $token,
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        // $req = Request::createFromEnvironment($env);
        $request = $request->withParsedBody([
            'name' => 'smile',
            'chars' => 's',
            'keywords' => 'These are some of the keywords. I,.,)( &*^%96 I realy liked',
            'category' => 'expressions'
        ]);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"message":"The new emoji has been created successfully.","data":null}';
        $this->assertSame($expected, $result);
    }

    public function testDeleteEmoji()
    {
        $token = $this->createToken('roy');
        Emoji::truncate();
        $this->populateEmoji();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'DELETE',
            'REQUEST_URI' => '/emogis/2',
            'HTTP_AUTHORIZATION' => $token,
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"message":"The Emogi has been deleted.","data":null}';
        $this->assertSame($expected, $result);
    }

    public function testDeleteEmojiFailsForNoID()
    {
        $token = $this->createToken('roy');
        Emoji::truncate();
        $this->populateEmoji();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'DELETE',
            'REQUEST_URI' => '/emogis/50',
            'HTTP_AUTHORIZATION' => $token,
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"message":"Cannot find the emoji to delete.","data":null}';
        $this->assertSame($expected, $result);
    }

    public function testRegister()
    {
        $token = $this->createToken('roy');
        User::truncate();
        // $this->populateUser();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/auth/register',
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $request = $request->withParsedBody([
            'username' => 'roy',
            'password' => 'ceejay',
        ]);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"message":"New user has been created successfully.","data":null}';
        $this->assertSame($expected, $result);
    }

    public function testLogin()
    {
        $token = $this->createToken('roy');
        User::truncate();
        $this->populateUser();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/auth/login',
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $request = $request->withParsedBody([
            'username' => 'roy',
            'password' => 'ceejay',
        ]);

        $request = $request->withAttribute('TokenTime', 1440302375);

        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"token":"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJpYXQiOjE0NDAzMDIzNzUsImp0aSI6Ik1UUTBNRE13TWpNM05RPT0iLCJuYmYiOjE0NDAzMDIzODUsImV4cCI6MTQ0MDMwNDM4NSwiZGF0YSI6eyJ1c2VybmFtZSI6InJveSJ9fQ.fr0N3p3QCjfSHtrW5HjodUTAgoP-m8tx-dRkBvsa0YS6FFSYXdi0yRzG1jtgzRjIAs9odwSEq_woBUkQfisysQ"}';
        $this->assertSame($expected, $result);
    }

    public function testLogout()
    {
        $token = $this->createToken('roy');
        Emoji::truncate();
        $this->populateEmoji();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/auth/logout',
            'HTTP_AUTHORIZATION' => $token,
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"message":"Successfully Logged Out","data":null}';
        $this->assertSame($expected, $result);
    }

    public function testPutUpdates()
    {
        $token = $this->createToken('roy');
        Emoji::truncate();
        $this->populateEmoji();
        User::truncate();
        $this->populateUser();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/emogis/2',
            'HTTP_AUTHORIZATION' => $token,
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $request = $request->withParsedBody([
            'name' => 'frownie',
            'chars' => 'f',
            'keywords' => 'f frown frownie',
            'category' => 'facial expressions'
        ]);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"message":"The Emogi has been updated successfully.","data":null}';
        $this->assertSame($expected, $result);
    }

    public function testCheckExpiredToken()
    {
        $token = $this->createToken('roy', time() - 10000);
        Emoji::truncate();
        $this->populateEmoji();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/emogis/2',
            'HTTP_AUTHORIZATION' => $token,
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $request = $request->withParsedBody([
            'name' => 'frownie',
            'chars' => 'f',
            'keywords' => 'f frown frownie',
            'category' => 'facial expressions'
        ]);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"message":"Token is Expired. Please re-login.","data":null}';
        $this->assertSame($expected, $result);
    }

    public function testForAuthorisationHeader()
    {
        Emoji::truncate();
        $this->populateEmoji();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/emogis/2',
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $request = $request->withParsedBody([
            'name' => 'frownie',
            'chars' => 'f',
            'keywords' => 'f frown frownie',
            'category' => 'facial expressions'
        ]);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"message":"Bad Request - Token not found in request. Please Login","data":null}';
        $this->assertSame($expected, $result);
    }


    public function testForTokenInAuthorisationHeader()
    {
        Emoji::truncate();
        $this->populateEmoji();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/emogis/2',
            'HTTP_AUTHORIZATION' => '',
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $request = $request->withParsedBody([
            'name' => 'frownie',
            'chars' => 'f',
            'keywords' => 'f frown frownie',
            'category' => 'facial expressions'
        ]);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"message":"Please Provide Token From Login","data":null}';
        $this->assertSame($expected, $result);
    }

    public function testUserIsLoggedOut()
    {
        $token = $this->createToken('roy');
        Emoji::truncate();
        $this->populateEmoji();
        $user = User::where('username', 'roy')->first();
        $user->tokenID = 'MTQ2MDI0MjE4MA==';
        $user->save();
        $action = new App();
        $environment = \Slim\Http\Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/emogis',
            'HTTP_AUTHORIZATION' => $token,
            ]
        );
        $request = \Slim\Http\Request::createFromEnvironment($environment);
        $request = $request->withParsedBody([
            'name' => 'frownie',
            'chars' => 'f',
            'keywords' => 'f frown frownie',
            'category' => 'facial expressions'
        ]);
        $response = new \Slim\Http\Response();
        $response = $action($request, $response);

        $result = ((string) $response->getBody());
        $expected = '{"message":"Please Re-login.","data":null}';
        $this->assertSame($expected, $result);
    }


    private function createToken($username, $time = null)
    {
        $time = $time === null ? (time() - 10) : $time;
        $tokenId = base64_encode('roy');
        $issuedAt = $time;
        $notBefore  = $issuedAt + 10;
        $expire     = $notBefore + 2000;
        $secretKey = base64_decode('sampleSecret'); // or get the app key from the config file.
        $JWTToken = [
            'iat'  => $issuedAt,
            'jti'  => $tokenId,
            'nbf'  => $notBefore,
            'exp'  => $expire,
            'data' => ['username' => $username],
        ];

        $jwt = JWT::encode(
            $JWTToken,      //Data to be encoded in the JWT
            $secretKey, // The signing key
            'HS512'     // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
        );

        return $jwt;
    }
}