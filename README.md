# PSR-7 Localization Middleware

[![Build Status](https://travis-ci.org/tboronczyk/localization-middleware.svg?branch=master)](https://travis-ci.org/tboronczyk/localization-middleware) [![codecov](https://codecov.io/gh/tboronczyk/localization-middleware/branch/master/graph/badge.svg)](https://codecov.io/gh/tboronczyk/localization-middleware)
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bhttps%3A%2F%2Fgithub.com%2Ftboronczyk%2Flocalization-middleware.svg?type=shield)](https://app.fossa.io/projects/git%2Bhttps%3A%2F%2Fgithub.com%2Ftboronczyk%2Flocalization-middleware?ref=badge_shield)

PSR-7 middleware to assist primarily with language-based content negotiation
and various other localization tasks. It determines the appropriate locale
based on the client’s request and sets an attribute on the request object to
make the value available to the rest of your application. Its callback hook
offers a convenient way to initialize other libraries or execute code based on
the locale value.

## Basic Example

    use Boronczyk\LocalizationMiddleware;

    $availableLocales = ['en_US', 'fr_CA', 'es_MX', 'eo'];
    $defaultLocale = 'en_US';
    $app->add(new LocalizationMiddleware($availableLocales, $defaultLocale));

    $app->get('/', function ($req, $resp, $args) {
        $attrs = $req->getAttributes();
        $locale = $attrs['locale'];
        return $resp->write("The locale is $locale.");
    });

## More Advanced Example

    use Boronczyk\LocalizationMiddleware;

    $availableLocales = ['en_US', 'fr_CA', 'es_MX', 'eo'];
    $defaultLocale = 'en_US';
    $middleware = new LocalizationMiddleware($availableLocales, $defaultLocale);

    $middleware->setSearchOrder([
        LocationMiddleware::FROM_URI_PATH,
        LocationMiddleware::FROM_URI_PARAM,
        LocationMiddleware::FROM_COOKIE,
        LocationMiddleware::FROM_HEADER
    ]);
    $middleware->setCallback(function (string $locale) {
        putenv("LANG=$locale");
        setlocale(LC_ALL, $locale);
        bindtextdomain('messages', 'Locale');
        bind_textdomain_codeset('messages', 'UTF-8');
        textdomain('messages');
    });
    $middleware->setUriParamName('hl');

    $app->add($middleware);

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

  * `setCookieEnabled(bool $enabled)`  
    Enable the locale cookie. The default value is TRUE.

        $middleware->setCookieEnabled(FALSE); // Disabled

  * `setCallback(callable $func)`  
    Sets a callback that is invoked after the middleware identifies the locale,
    offering the developer a chance to conveniently initialize other libraries
    or execute other code with the value. The callable’s signature is:
    `function (string $locale)`.

        $middleware->setCallback(function (string $locale) {
            putenv("LANG=$locale");
            setlocale(LC_ALL, $locale);
            bindtextdomain('messages', 'Locale');
            bind_textdomain_codeset('messages', 'UTF-8');
            textdomain('messages');
        });


## License
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bhttps%3A%2F%2Fgithub.com%2Ftboronczyk%2Flocalization-middleware.svg?type=large)](https://app.fossa.io/projects/git%2Bhttps%3A%2F%2Fgithub.com%2Ftboronczyk%2Flocalization-middleware?ref=badge_large)
