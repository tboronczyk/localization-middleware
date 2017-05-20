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

    public function testLocaleFromParam()
    {
        $req = self::createRequest(['QUERY_STRING' => 'locale=es_MX']);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('es_MX', $req->getAttribute('locale'));
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

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('fr_CA', $req->getAttribute('locale'));
    }

    public function testLocaleSetToCookie()
    {
        $req = self::createRequest(['QUERY_STRING' => 'locale=es_MX']);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertContains('es_MX', $resp->getHeaderLine('Set-Cookie'));
    }

    public function testLocaleFromHeader()
    {
        $req = self::createRequest(['HTTP_ACCEPT_LANGUAGE' => 'en_US,fr_CA;q=0.9']);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('fr_CA', $req->getAttribute('locale'));
    }

    public function testLocaleFromDefault()
    {
        $env = [
            'QUERY_STRING' => 'locale=pt_BR',
            'HTTP_ACCEPT_LANGUAGE' => 'pt_BR'
        ];
        $req = self::createRequest($env);
        $resp = self::createResponse();

        $ref = new ReflectionClass($req);
        $prop = $ref->getProperty('cookies');
        $prop->setAccessible(true);
        $prop->setValue($req, ['locale' => 'pt_BR']);

        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);

        list($req, $resp) = $lmw->__invoke($req, $resp, self::callable());
        $this->assertEquals('en_US', $req->getAttribute('locale'));
    }

    public function testLocaleSetToEnv()
    {
        $req = self::createRequest(['QUERY_STRING' => 'locale=es_MX']);
        $resp = self::createResponse();
        $lmw = new LocalizationMiddleware(self::$availableLocales, self::$defaultLocale);
        $lmw->__invoke($req, $resp, self::callable());

        $this->assertEquals('es_MX', getenv('LANG'));
    }
}
