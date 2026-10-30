# codeception-module-webdriver-bidi

A Codeception module that runs beside the WebDriver module and adds WebDriver BiDi
to its session, through [rnscommerce/php-webdriver-bidi](https://github.com/rnscommerce/php-webdriver-bidi).

Every session the WebDriver module starts asks for `webSocketUrl`, and the module
connects to it, following the session when the WebDriver module restarts the
browser. Over that connection it follows each window's requests and DOM changes,
which is what `waitForPageReady` waits on, and it keeps tests that share a browser
from seeing each other's cookies, storage and windows.

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

## A user context for each test

With the WebDriver module's `restart: false`, the tests of a suite share one
browser. The WebDriver module clears the current page's cookies after each test,
but cookies on other hosts, storage, the cache and the windows a test opened are
all there for the next one.

So when tests share a browser, each one runs in a user context of its own: a
profile inside the browser, with its own cookies, storage and cache. The test
starts in a single blank tab in it. After the test, the module moves the WebDriver
module to the next test's user context and removes the finished one, closing every
window in it, whether the test passed or failed.

- It follows from the WebDriver module's config. With `restart: true` every test
  has a new browser anyway, and with `clear_cookies: false` tests are meant to
  share cookies, so neither gets a user context.
- Not in Firefox. Its WebDriver cookie commands read and write the default user
  context's cookies whichever tab is current, so in a user context `seeCookie`,
  `grabCookie`, `resetCookie`, `setCookie` and session snapshots would miss the
  page's cookies. In Firefox, tests share the browser as they would without this
  module.
- Session snapshots keep working: `loadSessionSnapshot` sets the saved cookies
  in the current test's user context.

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
