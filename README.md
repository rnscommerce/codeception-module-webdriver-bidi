# codeception-module-webdriver-bidi

A Codeception module that runs beside the WebDriver module and adds WebDriver BiDi
to its session, through [rnscommerce/php-webdriver-bidi](https://github.com/rnscommerce/php-webdriver-bidi).

Every session the WebDriver module starts asks for `webSocketUrl`, and the module
connects to it, following the session when the WebDriver module restarts the
browser. Over that connection it follows each window's requests and DOM changes,
which is what `waitForPageReady` waits on.

## Enable it

List it after the WebDriver module, or after a module that extends it, such as
MFTF's `MagentoWebDriver`:

```yaml
modules:
    enabled:
        - WebDriver:
              url: http://localhost/
              browser: chrome
              wait: 10
        - \Rnscommerce\Codeception\WebDriverBiDi\WebDriverBiDi
```

The order matters: Codeception runs `_before` hooks in the order modules are
listed, and the WebDriver module starts its session in its own `_before`. Listed
first, this module refuses to start.

## Wait for the page

```php
$I->click('Save');
$I->waitForPageReady();
```

`waitForPageReady` waits until the current window has no request in flight, then
until neither its network nor its DOM has changed for 100 ms, then for its running
CSS transitions and animations to end, checking again after any it waited for.

- A request older than 10 s, such as a long poll, is no longer waited for.
- A DOM that keeps changing is no longer waited for once the network has been
  quiet for 1 s.
- Requests and changes in an iframe count for the window it is in; another
  window's do not.

It fails when the page is still busy after its timeout, in seconds: the argument
when given, otherwise the WebDriver module's `wait`. With `wait` at its default of
0, give it a timeout.

## What it needs from the driver

A driver that speaks WebDriver BiDi. It is tested with ChromeDriver 156 and
geckodriver 0.37 with Firefox 158.

## Development

```sh
composer install
composer check
CHROMEDRIVER=… CHROME_BINARY=… GECKODRIVER=… composer test:integration
```

`composer test:integration` runs the Codeception project in `tests/Integration/Project`
against headless Chrome and Firefox, skipping a browser whose driver is not named.
`CHROME_BINARY` is optional.
