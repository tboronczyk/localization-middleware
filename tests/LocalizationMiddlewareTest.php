<?php
declare(strict_types=1);

namespace Boronczyk\Tests;

error_reporting(E_ALL);
ini_set('display_errors', '1');

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Error\Deprecated;

use Slim\Psr7\Factory\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface as Request;

use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Boronczyk\LocalizationMiddleware;

class LocalizationMiddlewareTest extends TestCase
{
    protected static $availableLocales = ['en_US', 'fr_CA', 'es_MX', 'eo'];
    protected static $defaultLocale = 'en_US';

    protected static function createMiddleware(): LocalizationMiddleware
    {
        return new LocalizationMiddleware(
            self::$availableLocales,
            self::$defaultLocale
        );
    }

    protected static function createRequest(string $uri): Request
    {
        $factory = new ServerRequestFactory;
        return $factory->createServerRequest('GET', $uri);
    }

    protected static function createResponse(): Response
    {
        $factory = new ResponseFactory;
        return $factory->createResponse();
    }

    protected static function createHandler(): RequestHandler
    {
        return new class (self::createResponse()) implements RequestHandler
        {
            public $req;
            public $resp;
            
            public function __construct(Response $resp)
            {
                $this->resp = $resp;
            }

            public function handle(Request $req): Response
            {
                $this->req = $req;
                return $this->resp;
            }
        };
    }

    public function testLocaleFromUriPath()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_URI_PATH
        ]);

        $req = self::createRequest('/fr_CA/foo/bar');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('fr_CA', $handler->req->getAttribute('locale'));
    }

    public function testLocaleFromUriParam()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_URI_PARAM
        ]);

        $req = self::createRequest('/?locale=fr_CA');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('fr_CA', $handler->req->getAttribute('locale'));
    }

    public function testUriParamName()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_URI_PARAM
        ]);
        $mw->setUriParamName('lang');

        $req = self::createRequest('/?lang=fr_CA');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('fr_CA', $handler->req->getAttribute('locale'));
    }

    public function testLocaleFromSearchCallback()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_CALLBACK
        ]);
        $mw->setSearchCallback(function (Request $req): string {
            return 'fr_CA';
        });

        $req = self::createRequest('/');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('fr_CA', $handler->req->getAttribute('locale'));
    }

    public function testLocaleFromCookie()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_COOKIE
        ]);

        $req = self::createRequest('/')
            ->withCookieParams(['locale' => 'fr_CA']);
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('fr_CA', $handler->req->getAttribute('locale'));
    }

    public function testLocaleToCookie()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_URI_PARAM,
            LocalizationMiddleware::FROM_COOKIE
        ]);

        $req = self::createRequest('/?locale=fr_CA');
        $handler = self::createHandler();
        $resp = $mw->process($req, $handler);

        $this->assertContains('locale=fr_CA', $resp->getHeaderLine('Set-Cookie'));
    }

    public function testLocaleCookieName()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_COOKIE
        ]);
        $mw->setCookieName('lang');

        $req = self::createRequest('/')
            ->withCookieParams(['lang' => 'fr_CA']);
        $handler = self::createHandler();
        $resp = $mw->process($req, $handler);

        $this->assertContains('lang=fr_CA', $resp->getHeaderLine('Set-Cookie'));
    }

    public function testCookieNotCreated()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_URI_PARAM
        ]);

        $req = self::createRequest('/?locale=fr_CA');
        $handler = self::createHandler();
        $resp = $mw->process($req, $handler);

        $this->assertFalse($resp->hasHeader('Set-Cookie'));
    }

    public function testLocaleCallback()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_URI_PATH
        ]);

        $resolved = null;
        $mw->setLocaleCallback(function (string $locale) use (&$resolved) {
            $resolved = $locale;
        });

        $req = self::createRequest('/fr_CA/foo/bar');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('fr_CA', $resolved);
    }

    /**
     * @dataProvider localeFromHeaderDataProvided
     */
    public function testLocaleFromHeader(string $header, string $expectedResult)
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_HEADER
        ]);

        $req = self::createRequest('/')
            ->withAddedHeader('HTTP_ACCEPT_LANGUAGE', $header);
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals($expectedResult, $handler->req->getAttribute('locale'));
    }

    public function localeFromHeaderDataProvided(): array
    {
        return [
            // header value, expected value
            ['fr_CA', 'fr_CA'],
            ['en, *;q=0.7', self::$defaultLocale]
        ];
    }

    public function testLocaleFromHeaderQuality()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_HEADER
        ]);

        $req = self::createRequest('/')
            ->withAddedHeader('HTTP_ACCEPT_LANGUAGE', 'fr_CA,es_MX;q=0.8');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('fr_CA', $handler->req->getAttribute('locale'));
    }

    public function testLocaleFromHeaderQualitySorted()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_HEADER
        ]);

        $req = self::createRequest('/')
            ->withAddedHeader('HTTP_ACCEPT_LANGUAGE', 'fr_CA;q=0.7,en_US;q=0.2,es_MX;q=0.8');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('es_MX', $handler->req->getAttribute('locale'));
    }

    public function testLocaleFromHeaderQualitySortedDefault()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_HEADER
        ]);

        $req = self::createRequest('/')
            ->withAddedHeader('HTTP_ACCEPT_LANGUAGE', 'en_US;q=0.2,fr_CA,es_MX;q=0.8');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('fr_CA', $handler->req->getAttribute('locale'));
    }

    public function testLocaleFromHeaderQualitySame()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_HEADER
        ]);

        $req = self::createRequest('/')
            ->withAddedHeader('HTTP_ACCEPT_LANGUAGE', 'fr_CA;q=0.8,es_MX;q=0.8');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('fr_CA', $handler->req->getAttribute('locale'));
    }

    public function testLocaleLanguageDowngrade()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_HEADER
        ]);

        $req = self::createRequest('/')
            ->withAddedHeader('HTTP_ACCEPT_LANGUAGE', 'en,eo_XX');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('eo', $handler->req->getAttribute('locale'));
    }

    public function testLocaleDefault()
    {
        $mw = self::createMiddleware();

        $req = self::createRequest('/pt_BR/foo/bar?locale=pt_BR')
            ->withAddedHeader('HTTP_ACCEPT_LANGUAGE', 'pt_BR')
            ->withCookieParams(['locale' => 'pt_BR']);
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('en_US', $handler->req->getAttribute('locale'));
    }

    public function testLocaleDefaultMissingHeader()
    {
        $mw = self::createMiddleware();

        $req = self::createRequest('/')
            ->withoutHeader('HTTP_ACCEPT_LANGUAGE');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('en_US', $handler->req->getAttribute('locale'));
    }

    public function testReqAttrName()
    {
        $mw = self::createMiddleware();
        $mw->setReqAttrName('lang');

        $req = self::createRequest('/?locale=fr_CA');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('fr_CA', $handler->req->getAttribute('lang'));
    }

    public function testSearchOrder()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_URI_PATH,
            LocalizationMiddleware::FROM_HEADER,
            LocalizationMiddleware::FROM_URI_PARAM
        ]);

        $req = self::createRequest('/pt_BR/foo/bar?locale=fr_CA')
            ->withAddedHeader('HTTP_ACCEPT_LANGUAGE', 'es_MX');
        $handler = self::createHandler();
        $mw->process($req, $handler);

        $this->assertEquals('es_MX', $handler->req->getAttribute('locale'));
    }

    public function testSearchOrderException()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([999]);

        $req = self::createRequest('/');
        $handler = self::createHandler();

        $this->expectException(\DomainException::class);
        $mw->process($req, $handler);
    }

    public function testLocaleFromCallbackException()
    {
        $mw = self::createMiddleware();
        $mw->setSearchOrder([
            LocalizationMiddleware::FROM_CALLBACK
        ]);

        $req = self::createRequest('/');
        $handler = self::createHandler();

        $this->expectException(\LogicException::class);
        $mw->process($req, $handler);
    }
}
