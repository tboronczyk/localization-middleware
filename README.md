# Slim 3 Localization Middleware

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
