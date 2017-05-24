# PSR-7 Localization Middleware

[![Build Status](https://travis-ci.org/tboronczyk/localization-middleware.svg?branch=master)](https://travis-ci.org/tboronczyk/localization-middleware) [![codecov](https://codecov.io/gh/tboronczyk/localization-middleware/branch/master/graph/badge.svg)](https://codecov.io/gh/tboronczyk/localization-middleware)

PSR-7 middleware to assist primarily with language-based content negotiation
and various other localization tasks.

## Usage

    use Boronczyk\LocalizationMiddleware;

    $availableLocales = ['en_US', 'fr_CA', 'es_MX', 'eo'];
    $defaultLocale = 'en_US';
    $app->add(new LocalizationMiddleware($availableLocales, $defaultLocale));

    $app->get('/', function ($req, $resp, $args) {
        $attrs = $req->getAttributes();
        $locale = $attrs['locale'];
    });

## Configurable Behavior

The middleware component’s behavior is configurable though the following
methods:

  * `setAvailableLocales(array $locales)`  
    Sets the list of available locales after an instance has already been
    created.

        $middleware->setAvailableLocales(['en_US', 'fr_CA', 'pt_BR']);

  * `setDefaultLocale(string $locale)`  
    Sets the default locale to return after an instance has already been
    created.

        $middleware->setDefaultLocale('fr_CA');

  * `setSearchOrder(array $order)`  
    Sets the order in which inputs are searched for a suitable locale.

        $middleware->setSearchOrder([
            LocationMiddleware::FROM_URI_PATH,
            LocationMiddleware::FROM_URI_PARAM,
            LocationMiddleware::FROM_COOKIE,
            LocationMiddleware::FROM_HEADER
        ]);

    Adding or removing locale sources from the order modifies the search
    domain.

        // only search cookies and the Accept-Language header
        $middleware->setSearchOrder([
            LocationMiddleware::FROM_COOKIE,
            LocationMiddleware::FROM_HEADER
        ]);

    The available local source constants are:

    * `LocationMiddleware::FROM_URI_PATH`  
      Search for the locale in the URI path. The first directory value in
      the request path is considered the locale, for example 
      `https://example.com/en_US/foo`.

    * `LocationMiddleware::FROM_URI_PARAM`  
      Search for the locale in the URI parameter (the default parameter name
      is `locale`).

    * `LocationMiddleware::FROM_COOKIE`  
      Search for the locale in cookies (the default cookie name is `locale`).

    * `LocationMiddleware::FROM_HEADER`  
      Search for the local in the HTTP `Accept-Language` header. Header
      searches make a best-effort search for locales, languages, and possible
      quality modifiers.

    The default order is: `FROM_URI_PATH`, `FROM_URI_PARAM`, `FROM_COOKIE`,
    `FROM_HEADER`.

  * `setReqAttrName(string $name)`  
    Sets the name for the attribute attached to the request. The default name
    is `locale`.

        $middleware->setReqAttrName('lang');

        $app->get('/', function ($req, $resp, $args) {
            $attrs = $req->getAttributes();
            $lang = $attrs['lang'];
        });

  * `setUriParamName(string $name)`  
    Sets the name for a URI parameter to specify the locale. The default name
    is `locale`.

        $middleware->setReqAttrName('lang');

        https://example.com/mypage?lang=es_MX

  * `setCookieName(string $name)`  
    Sets the name of the cookie to store the determined locale. The default
    name is `locale`.

        $middleware->setCookieName('lang');

  * `setCookiePath(string $path)`  
    Sets the path of the cookie for which it will be returned by the client.
    The default path is `/`.

        $middleware->setCookiePath("/dir");

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
