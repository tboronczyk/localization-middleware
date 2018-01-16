<?php
declare(strict_types=1);

namespace Boronczyk;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Middleware to assist primarily with language-based content negotiation
 * and various other localization tasks
 */
class LocalizationMiddleware
{
    const FROM_URI_PATH = 1;
    const FROM_URI_PARAM = 2;
    const FROM_COOKIE = 3;
    const FROM_HEADER = 4;

    protected $availableLocales;
    protected $defaultLocale;

    protected $searchOrder;

    protected $reqAttrName;
    protected $uriParamName;
    protected $cookieName;
    protected $cookiePath;
    protected $cookieExpire;
    protected $cookieEnabled;

    protected $callback;

    /**
     * @param array $locales a list of available locales
     * @param string $default the default locale
     */
    public function __construct(array $locales, string $default)
    {
        $this->setAvailableLocales($locales);
        $this->setDefaultLocale($default);
        $this->setSearchOrder(
            [self::FROM_URI_PATH, self::FROM_URI_PARAM, self::FROM_COOKIE, self::FROM_HEADER]
        );
        $this->setReqAttrName('locale');
        $this->setUriParamName('locale');
        $this->setCookieName('locale');
        $this->setCookiePath('/');
        $this->setCookieExpire(3600 * 24 * 30); // 30 days
        $this->setCookieEnabled(TRUE);
        $this->setCallback(function () { /* empty function */ });
    }

    /**
     * @param array $locales a list of available locales
     */
    public function setAvailableLocales(array $locales)
    {
        $this->availableLocales = [];
        foreach ($locales as $locale) {
            $this->availableLocales[] = $this->parseLocale($locale);
        }
    }

    /**
     * @param string $default the default locale
     */
    public function setDefaultLocale(string $default)
    {
        $this->defaultLocale = $default;
    }

    /**
     * @param array $order the order in which the search will be performed to
     *        resolve the locale
     */
    public function setSearchOrder(array $order)
    {
        $this->searchOrder = $order;
    }

    /**
     * @param string $name the name for the attribute attached to the request
     */
    public function setReqAttrName(string $name)
    {
        $this->reqAttrName = $name;
    }

    /**
     * @param string $name the name for the locale URI parameter
     */
    public function setUriParamName(string $name)
    {
        $this->uriParamName = $name;
    }

    /**
     * @param string $name the name for the locale cookie
     */
    public function setCookieName(string $name)
    {
        $this->cookieName = $name;
    }

    /**
     * @param string $path the locale cookie's path
     */
    public function setCookiePath(string $path)
    {
        $this->cookiePath = $path;
    }

    /**
     * @param int $secs cookie expiration in seconds from now
     */
    public function setCookieExpire(int $secs)
    {
        $this->cookieExpire = gmdate('D, d M Y H:i:s T', time() + $secs);
    }

    /**
     * @param bool $enabled set the cookie or not
     */
    public function setCookieEnabled(bool $enabled)
    {
        $this->cookieEnabled = $enabled;
    }

    /**
     * @param callable $func callable to invoke when locale is determined
     */
    public function setCallback(callable $func)
    {
        $this->callback = $func;
    }

    /**
     * Add the locale to the environment, request and response objects
     */
    public function __invoke(Request $req, Response $resp, callable $next)
    {
        $locale = $this->getLocale($req);

        $this->callback->__invoke($locale);

        $req = $req->withAttribute($this->reqAttrName, $locale);

        if ($this->cookieEnabled) {
            $resp = $resp->withHeader(
                'Set-Cookie',
                "{$this->cookieName}=$locale" .
                "; Path={$this->cookiePath}" .
                "; Expires={$this->cookieExpire}"
            );
        }

        return $next($req, $resp);
    }

    protected function getLocale(Request $req)
    {
        foreach ($this->searchOrder as $order) {
            switch ($order) {
                case self::FROM_URI_PATH:
                    $locale = $this->localeFromPath($req);
                    break;

                case self::FROM_URI_PARAM:
                    $locale = $this->localeFromParam($req);
                    break;

                case self::FROM_COOKIE:
                    $locale = $this->localeFromCookie($req);
                    break;

                case self::FROM_HEADER:
                    $locale = $this->localeFromHeader($req);
                    break;

                default:
                    throw new \DomainException('Unknown search option provided');
            }
            if (!empty($locale)) {
                return $locale;
            }
        }
        return $this->defaultLocale;
    }

    protected function localeFromPath(Request $req): string
    {
        list(, $value) = explode('/', $req->getUri()->getPath());
        return $this->filterLocale($value);
    }

    protected function localeFromParam(Request $req): string
    {
        $params = $req->getQueryParams();
        $value = $params[$this->uriParamName] ?? '';
        return $this->filterLocale($value);
    }

    protected function localeFromCookie(Request $req): string
    {
        $cookies = $req->getCookieParams();
        $value = $cookies[$this->cookieName] ?? '';
        return $this->filterLocale($value);
    }

    protected function localeFromHeader(Request $req): string
    {
        $values = $this->parse($req->getHeaderLine('Accept-Language'));
        usort($values, [$this, 'sort']);
        foreach ($values as $value) {
            $value = $this->filterLocale($value['locale']);
            if (!empty($value)) {
                return $value;
            }
        }
        // search language if a full locale is not found
        foreach ($values as $value) {
            $value = $this->filterLocale($value['language']);
            if (!empty($value)) {
                return $value;
            }
        }
        return '';
    }

    protected function filterLocale(string $locale): string
    {
        // return the locale if it is available
        foreach ($this->availableLocales as $avail) {
            if ($locale == $avail['locale']) {
                return $locale;
            }
        }
        return '';
    }

    protected function parse(string $header): array
    {
        // the value may contain multiple languages separated by commas,
        // possibly as locales (ex: en_US) with quality (ex: en_US;q=0.5)
        $values = [];
        foreach (explode(',', $header) as $lang) {
            @list($locale, $quality) = explode(';', $lang, 2);
            $val = $this->parseLocale($locale);
            $val['quality'] = $this->parseQuality($quality ?? '');
            $values[] = $val;
        }
        return $values;
    }

    protected function parseLocale(string $locale): array
    {
        // Locale format: language[_territory[.encoding[@modifier]]]
        //
        // Language and territory should be separated by an underscore
        // although sometimes a hyphen is used. The language code should
        // be lowercase. Territory should be uppercase. Take this into
        // account but normalize the returned string as lowercase,
        // underscore, uppercase.
        //
        // The possible codeset and modifier is discarded since the header
        // *should* really list languages (not locales) in the first place
        // and the chances of needing to present content at that level of
        // granularity are pretty slim.
        $lang = '([[:alpha:]]{2})';
        $terr = '([[:alpha:]]{2})';
        $code = '([-\\w]+)';
        $mod  = '([-\\w]+)';
        $regex = "/$lang(?:[-_]$terr(?:\\.$code(?:@$mod)?)?)?/";
        preg_match_all($regex, $locale, $m);

        $locale = $language = strtolower($m[1][0]);
        if (!empty($m[2][0])) {
            $locale .= '_' . strtoupper($m[2][0]);
        }

        return [
            'locale' => $locale,
            'language' => $language
        ];
    }

    protected function parseQuality(string $quality): float
    {
        // If no quality is given then return 1 as this is the default quality
        // defined in RFC 2616 HTTP/1.1 section 14.4 Accept-Language
        // See https://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
        @list(, $value) = explode('=', $quality, 2);
        return (float)($value ?: 1.0);
    }

    protected function sort(array $a, array $b): int
    {
        // Sort order is determined first by quality (higher values are
        // placed first) then by order of their apperance in the header.
        if ($a['quality'] < $b['quality']) {
            return 1;
        }
        if ($a['quality'] == $b['quality']) {
            return 0;
        }
        return -1;
    }
}
