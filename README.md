# Slim 3 Localization Middleware

[![Build Status](https://travis-ci.org/tboronczyk/localization-middleware.svg?branch=master)](https://travis-ci.org/tboronczyk/localization-middleware) [![codecov](https://codecov.io/gh/tboronczyk/localization-middleware/branch/master/graph/badge.svg)](https://codecov.io/gh/tboronczyk/localization-middleware)

Slim 3 middleware to assist primarily with language-based content negotiation
and various other localization tasks.

## Usage

    use Boronczyk\LocalizationMiddleware;

    $availableLocales = ['en_US', 'fr_CA', 'es_MX', 'eo'];
    $defaultLocale = 'en_US';
    $app->add(new LocalizationMiddleware($availableLocales, $defaultLocale));

    $app->get('/', function ($req, $resp, $args) {
        $locale = $req->getAttribute('locale');
    });

## Configurable Behavior

The middleware component’s behavior is configurable though the following
methods:

  * `setAvailableLocales(array $locales)`  
    Sets the list of available locales after an instance has already been
    created.

        $middleware->setAvailableLocales(['en_US', 'en_GB', 'pt_BR']);

  * `setDefaultLocale(string $locale)`  
    Sets the default locale to return after an instance has already been
    created.

        $middleware->setDefaultLocale('en_GB');

  * `setSearchOrder(array $order)`  
    Sets the order in which inputs are searched for a suitable locale.
    The default order is: `FROM_URI_PARAM`, `FROM_COOKIE`, `FROM_HEADER`.

        $middleware->setSearchOrder([
            LocationMiddleware::FROM_COOKIE,
            LocationMiddleware::FROM_URI_PARAM,
            LocationMiddleware::FROM_HEADER
        ]);

  * `setReqAttrName(string $name)`  
    Sets the name for the attribute attached to the request. The default name
    is `locale`.

        $middleware->setReqAttrName('lang');

        $app->get('/', function ($req, $resp, $args) {
            $lang = $req->getAttribute('lang');
        });

  * `setUriParamName(string $name)`  
    Sets the name for a URI parameter to specify the locale. The default name
    is `locale`.

        $middleware->setReqAttrName('lang');

        https://example.com/mypage?lang=de_CH

  * `setCookieName(string $name)`  
    Sets the name of the cookie to store the determined locale. The default
    name is `locale`.

        $middleware->setCookieName('lang');

  * `setCookieExpire(int $secs)`  
    Sets the duration of the locale cookie. The default value is 30 days.

        $middleware->setCookieExpire(3600); // 1 hour

  * `registerGettext(bool $bool)`  
    Sets whether to automatically set up the locale for use with gettext.
    When set, the locale is set to the `LANG` environment variable and the
    `LC_ALL` catalog. The default value is `false`.

        $middleware->registerGettext(true);
        
        $app->get('/', function ($req, $resp, $args) {
            return $resp->write(_('Hello world'));
        });

  * `setTextDomain(string $domain)`  
    Sets the text domain for gettext. The default value is `messages`.

        $middleware->registerGettext(true);
        $middleware->setTextDomain('errors');

  * `setDirectory(string $directory)`  
    Sets the locale directory for gettext. The default value is `Locale`.

        $middleware->registerGettext(true);
        $middleware->setDirectory('Locale');
