# Localization Middleware

[![Build Status](https://travis-ci.org/tboronczyk/localization-middleware.svg?branch=master)](https://travis-ci.org/tboronczyk/localization-middleware) [![codecov](https://codecov.io/gh/tboronczyk/localization-middleware/branch/master/graph/badge.svg)](https://codecov.io/gh/tboronczyk/localization-middleware)
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bhttps%3A%2F%2Fgithub.com%2Ftboronczyk%2Flocalization-middleware.svg?type=shield)](https://app.fossa.io/projects/git%2Bhttps%3A%2F%2Fgithub.com%2Ftboronczyk%2Flocalization-middleware?ref=badge_shield)

Middleware to assist primarily with language-based content negotiation and 
various other localization tasks. It determines the appropriate locale based
on the client’s request and sets an attribute on the request object making the
value available to the rest of your application. Its callback hook offers a
convenient way to initialize other libraries or execute code based on the
locale value.

**Version 2 conforms to [PSR-15](https://www.php-fig.org/psr/psr-15/). Use
version ^1.4 if you require the so-called “Double Pass” approach using
`__invoke()`.**

## Installation

Localization Middleware is installable via [Composer](https://getcomposer.org).

    composer require boronczyk/localization-middleware

## Basic Example

Here is a basic usage example:

    use Boronczyk\LocalizationMiddleware;

    // register the middleware with your PSR-15 compliant framework
    $availableLocales = ['en_US', 'fr_CA', 'es_MX', 'eo'];
    $defaultLocale = 'en_US';
    $app->add(new LocalizationMiddleware($availableLocales, $defaultLocale));

    // reference the locale in your route callback
    $app->get('/', function ($req, $resp, $args) {
        $attrs = $req->getAttributes();
        $locale = $attrs['locale'];
        return $resp->write("The locale is $locale.");
    });

## More Advanced Example

Here is a more advanced usage example:

    use Boronczyk\LocalizationMiddleware;

    // instanciate the middleware
    $availableLocales = ['en_US', 'fr_CA', 'es_MX', 'eo'];
    $defaultLocale = 'en_US';
    $middleware = new LocalizationMiddleware($availableLocales, $defaultLocale);

    // specify the order in which inputs are searched for the locale
    $middleware->setSearchOrder([
        LocalizationMiddleware::FROM_CALLBACK,
        LocalizationMiddleware::FROM_URI_PATH,
        LocalizationMiddleware::FROM_URI_PARAM,
        LocalizationMiddleware::FROM_COOKIE,
        LocalizationMiddleware::FROM_HEADER
    ]);

    // attempt to identify the locale using a callback
    $middleware->setSearchCallback(
        function (Request $req) use (Container $c): string {
            $db = $c->get('GeoIp2Database');
            switch ($db->country($req->getAttribute('ip_address')) {
                case 'CA':
                    return 'fr_CA';
                case 'US':
                    return 'en_US';
                case 'MX':
                    return 'es_MX';
                default:
                    return '';
            }
        }
    );

    // execute logic once the locale has been identified
    $middleware->setLocaleCallback(function (string $locale) {
        putenv("LANG=$locale");
        setlocale(LC_ALL, $locale);
        bindtextdomain('messages', 'Locale');
        bind_textdomain_codeset('messages', 'UTF-8');
        textdomain('messages');
    });

    // change the name of the uri parameter identifying the locale
    $middleware->setUriParamName('hl');

    // register the middleware with your PSR-15 compliant framework
    $app->add($middleware);

    // reference the locale in your route callback
     $app->get('/', function ($req, $resp, $args) {
        $attrs = $req->getAttributes();
        $locale = $attrs['locale'];
        $text = sprintf(_('The locale is %s.'), $locale);
        return $resp->write($text);
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
            LocalizationMiddleware::FROM_URI_PATH,
            LocalizationMiddleware::FROM_URI_PARAM,
            LocalizationMiddleware::FROM_COOKIE,
            LocalizationMiddleware::FROM_HEADER
        ]);

    Adding or removing locale sources from the order modifies the search
    domain.

        // only search cookies and the Accept-Language header
        $middleware->setSearchOrder([
            LocalizationMiddleware::FROM_COOKIE,
            LocalizationMiddleware::FROM_HEADER
        ]);

    The available locale source constants are:

    * `LocalizationMiddleware::FROM_URI_PATH`  
      Search for the locale in the URI path. The first directory value in
      the request path is considered the locale, for example
      `https://example.com/en_US/foo`.

    * `LocalizationMiddleware::FROM_URI_PARAM`  
      Search for the locale in the URI parameter (the default parameter name
      is `locale`).

    * `LocalizationMiddleware::FROM_COOKIE`  
      Search for the locale in cookies (the default cookie name is `locale`).
      *Note: Using this will set a locale cookie for subsequent requests.*

    * `LocalizationMiddleware::FROM_HEADER`  
      Search for the locale in the HTTP `Accept-Language` header. Header
      searches make a best-effort search for locales, languages, and possible
      quality modifiers.

    * `LocalizationMiddleware::FROM_CALLBACK`  
      Search for the locale using a custom callback function. The callback
      function is set with `setSearchCallback()`.

    The default order is: `FROM_URI_PATH`, `FROM_URI_PARAM`, `FROM_COOKIE`,
    `FROM_HEADER`. *Note that `FROM_CALLBACK` is **not** included by default.*

  * `setSearchCallback(callable $func)`  
    Sets a callback that is invoked when searching for the locale, offering
    the developer a chance to inject a locale of their choosing into the
    search. The callable’s signature is: `function (Request $req): string`.

        $middleware->setSearchCallback(
            function (Request $req) use (Container $c): string {
                $db = $c->get('GeoIp2Database');
                switch ($db->country($req->getAttribute('ip_address')) {
                    case 'CA':
                        return 'fr_CA';
                    case 'US':
                        return 'en_US';
                    case 'MX':
                        return 'es_MX';
                    default:
                        return '';
                }
            }
        );

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

        $middleware->setUriParamName('lang');

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

  * `setLocaleCallback(callable $func)`  
    Sets a callback that is invoked after the middleware identifies the locale,
    offering the developer a chance to conveniently initialize other libraries
    or execute other code with the value. The callable’s signature is:
    `function (string $locale)`.

        $middleware->setLocaleCallback(function (string $locale) {
            putenv("LANG=$locale");
            setlocale(LC_ALL, $locale);
            bindtextdomain('messages', 'Locale');
            bind_textdomain_codeset('messages', 'UTF-8');
            textdomain('messages');
        });


## License
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bhttps%3A%2F%2Fgithub.com%2Ftboronczyk%2Flocalization-middleware.svg?type=large)](https://app.fossa.io/projects/git%2Bhttps%3A%2F%2Fgithub.com%2Ftboronczyk%2Flocalization-middleware?ref=badge_large)
