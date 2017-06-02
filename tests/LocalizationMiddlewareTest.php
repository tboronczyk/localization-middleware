<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

use PHPUnit\Framework\TestCase;

use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;
use Boronczyk\LocalizationMiddleware;

chdir(dirname(__FILE__));
require_once '../vendor/autoload.php';

class LocalizationMiddlewareTest extends TestCase
{
    protected static $availableLocales = ['en_US', 'fr_CA', 'es_MX', 'eo'];
    protected static $defaultLocale = 'en_US';

    protected static function createRequest(array $env)
    {
        return Request::createFromEnvironment(Environment::mock($env));
    }

    protected static function createResponse()
    {
        return new Response;
    }

    protected static function callable() {
        return function (Request $req, Response $res) {
            return [$req, $res];
        };
    }

    public function testLocaleFromUriPath()
    {
        $req = self::createRequest([
            'REQUEST_URI' => '/fr_CA/foo/bar'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_URI_PATH]);
         
        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('fr_CA', $req->getAttribute('locale'));
    }

    public function testLocaleFromUriParam()
    {
        $req = self::createRequest([
            'QUERY_STRING' => 'locale=fr_CA'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_URI_PARAM]);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('fr_CA', $req->getAttribute('locale'));
    }

    public function testUriParamName()
    {
        $req = self::createRequest([
            'QUERY_STRING' => 'lang=fr_CA'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_URI_PARAM]);
        $lmw->setUriParamName('lang');

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('fr_CA', $req->getAttribute('locale'));
    }

    public function testLocaleFromCookie()
    {
        $req = self::createRequest([]);
        $resp = self::createResponse();

        $ref = new ReflectionClass($req);
        $prop = $ref->getProperty('cookies');
        $prop->setAccessible(true);
        $prop->setValue($req, ['locale' => 'fr_CA']);

        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_COOKIE]);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('fr_CA', $req->getAttribute('locale'));
    }

    public function testLocaleToCookie()
    {
        $req = self::createRequest([
            'QUERY_STRING' => 'locale=fr_CA'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_URI_PARAM]);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertContains('locale=fr_CA', $resp->getHeaderLine('Set-Cookie'));
    }

    public function testLocaleCookieName()
    {
        $req = self::createRequest([]);
        $resp = self::createResponse();

        $ref = new ReflectionClass($req);
        $prop = $ref->getProperty('cookies');
        $prop->setAccessible(true);
        $prop->setValue($req, ['lang' => 'fr_CA']);

        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_COOKIE]);
        $lmw->setCookieName('lang');

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertContains('lang=fr_CA', $resp->getHeaderLine('Set-Cookie'));
    }

    public function testCallback()
    {
        $req = self::createRequest([
            'REQUEST_URI' => '/fr_CA/foo/bar'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_URI_PATH]);

        $resolved = null;
        $lmw->setCallback(function (string $locale) use (&$resolved) {
            $resolved = $locale;
        });
         
        $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('fr_CA', $resolved);
    }

    public function testLocaleFromHeader()
    {
        $req = self::createRequest([
            'HTTP_ACCEPT_LANGUAGE' => 'fr_CA'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_HEADER]);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('fr_CA', $req->getAttribute('locale'));
    }

    public function testLocaleFromHeaderQuality()
    {
        $req = self::createRequest([
            'HTTP_ACCEPT_LANGUAGE' => 'fr_CA,es_MX;q=0.8'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_HEADER]);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('es_MX', $req->getAttribute('locale'));
    }

    public function testLocaleFromHeaderQualitySorted()
    {
        $req = self::createRequest([
            'HTTP_ACCEPT_LANGUAGE' => 'fr_CA;q=0.7,en_US;q=0.2,es_MX;q=0.8'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_HEADER]);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('es_MX', $req->getAttribute('locale'));
    }

    public function testLocaleFromHeaderQualitySame()
    {
        $req = self::createRequest([
            'HTTP_ACCEPT_LANGUAGE' => 'fr_CA;q=0.8,es_MX;q=0.8'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_HEADER]);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('fr_CA', $req->getAttribute('locale'));
    }

    public function testLocaleLanguageDowngrade()
    {
        $req = self::createRequest([
            'HTTP_ACCEPT_LANGUAGE' => 'en,eo_XX'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([LocalizationMiddleware::FROM_HEADER]);
         
        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('eo', $req->getAttribute('locale'));
    }

    public function testLocaleDefault()
    {
        $req = self::createRequest([
            'REQUEST_URI' => '/pt_BR/foo/bar',
            'QUERY_STRING' => 'locale=pt_BR',
            'HTTP_ACCEPT_LANGUAGE' => 'pt_BR'
        ]);
        $resp = self::createResponse();

        $ref = new ReflectionClass($req);
        $prop = $ref->getProperty('cookies');
        $prop->setAccessible(true);
        $prop->setValue($req, ['locale' => 'pt_BR']);

        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('en_US', $req->getAttribute('locale'));
    }

    public function testReqAttrName()
    {
        $req = self::createRequest([
            'QUERY_STRING' => 'locale=fr_CA'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setReqAttrName('lang');

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('fr_CA', $req->getAttribute('lang'));
    }

    public function testSearchOrder()
    {
        $req = self::createRequest([
            'REQUEST_URI' => '/pt_BR/foo/bar',
            'QUERY_STRING' => 'locale=fr_CA',
            'HTTP_ACCEPT_LANGUAGE' => 'es_MX'
        ]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([
            LocalizationMiddleware::FROM_URI_PATH,
            LocalizationMiddleware::FROM_HEADER,
            LocalizationMiddleware::FROM_URI_PARAM
        ]);
         
        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('es_MX', $req->getAttribute('locale'));
    }

    public function testSearchOrderException()
    {
        $req = self::createRequest([]);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->setSearchOrder([999]);
         
        $this->expectException('DomainException');
        $lmw->__invoke($req, $resp, self::callable());
    }
}
