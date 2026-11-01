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

A helper's `_capabilities` function is given capabilities that read and write as
an array does at their top level, so `$capabilities['name'] = $name` works. A
nested option, such as `goog:chromeOptions`, is read, changed and set back whole.

## Wait for the page

```php
$I->click('Save');
$I->waitForPageReady();
```

`waitForPageReady` waits until the current window has no request in flight, then
until neither its network nor its DOM has changed for 100 ms, then for its running
CSS transitions and animations to end, checking again after any it waited for. A
page that has not changed for 100 ms already is ready at once.

- A request older than 10 s, such as a long poll, is no longer waited for.
- A request of a page navigated away from is no longer waited for, though Chrome
  never says it ended.
- A DOM that keeps changing is no longer waited for once the network has been
  quiet for 1 s of the wait.
- Requests and changes in an iframe count for the window it is in; another
  window's do not. Only the window's top document's animations are waited for,
  not an iframe's.

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
- Not where Chrome's download preferences are configured, in the `prefs` of
  `goog:chromeOptions`: Chrome saves nothing a test downloads in a user context.
- Session snapshots keep working: `loadSessionSnapshot` sets the saved cookies
  in the current test's user context.

## Act on an actionable element

Before an action on an element, the module finds the element as the action does
and waits until it is actionable, by [Playwright](https://playwright.dev/docs/actionability)'s
checks, taking only those without which WebDriver would refuse the action or take
it to no effect:

| Action | Waits until the element is |
| --- | --- |
| `click`, `checkOption`, `uncheckOption` | visible, stable, receiving events, enabled |
| `doubleClick`, `clickWithLeftButton` | stable, receiving events, enabled |
| `clickWithRightButton`, `moveMouseOver`, and both elements of `dragAndDrop` | stable, receiving events |
| `fillField`, `appendField`, `clearField` | visible, enabled, editable |
| `selectOption`, `unselectOption` | enabled |
| `attachFile`, `submitForm`, `pressKey` | in the page |

- Visible: neither it nor an ancestor is `display: none`, and it is not
  `visibility: hidden`.
- Stable: its box is the same over two animation frames.
- Receiving events: a click at its centre would hit it, something inside it or
  its label, scrolling it into view first when it is outside the window or its
  scrolling container. One not visible, such as a link shown only on hover, is
  left to the pointer.
- Enabled: not disabled, by itself or a disabled fieldset.
- Editable: not `readonly`.

The element is checked again whenever the page's DOM changes and on every
animation frame, and found again when a render replaces it. An element already
actionable is acted on at once. One that is not by `action_timeout`, 10 s by
default, fails the step with what holds it:

```
click waited 10 s for '#save', which is covered by <div class="modal-overlay"></div>
```

An element not found by then is left to the action, to fail as it would without
the module. Assertions, waits and `type` are not checked.

A suite whose own modules add actions names them in `actions`, each with the
action whose finding and checks it takes, and in `argument` the position of its
element when that is not the first:

```yaml
        - \Rnscommerce\Codeception\WebDriverBiDi\WebDriverBiDi:
              action_timeout: 10
              actions:
                  fillSecretField: {like: fillField}
                  rapidClick: {like: click}
                  clickInDialog: {like: click, argument: 1}
```

An action a module calls from inside its own, such as the `click` in MFTF's
`conditionalClick`, is not a step, so it is not checked. The module checks it by
passing the action and its arguments to `_waitUntilActionable` first, which waits
as a step of that action would and fails with the same message:

```php
$this->getModule('\\' . WebDriverBiDi::class)->_waitUntilActionable('click', [$selector]);
$this->click($selector);
```

## Waits that listen

The module replaces seven of the WebDriver module's waits: `waitForElement`,
`waitForElementVisible`, `waitForElementNotVisible`, `waitForElementClickable`,
`waitForElementChange`, `waitForText` and `waitForJS`. Each takes the same
arguments, checks the same condition with the WebDriver module's own locators and
fails with the same exception and message, so tests do not change.

Where the WebDriver module checks every 250 ms, these check once, then again only
when the page changes: a request is sent or ends, the DOM changes, something
loads, the document's ready state changes, or a CSS animation or transition
starts or ends. A wait returns within a few milliseconds of the change it was
waiting for.

Some changes reach the page with none of those, such as JavaScript state set by a
timer or a rule added to a style sheet. So a wait also checks 100 ms after the
page last changed, for what its scripts do next, and every 500 ms while nothing
changes at all.

Codeception gives an action to the module listed last that has it, which is this
one. Waits a module makes on its own behalf, such as MFTF's `waitForPageLoad`
calling `waitForJS`, are the WebDriver module's and still check every 250 ms.
A module that waits on its own behalf can find this one among the enabled modules
and call its waits and `waitForPageReady` instead, once `_hasBiDi()` says the
session has BiDi.

## Assertions that retry

The module answers the WebDriver module's assertions about the page, and checks
each again while it fails, as Playwright's [web-first assertions](https://playwright.dev/docs/test-assertions)
do. A check is the WebDriver module's own, run on the module listed before this
one, so the condition and the message are those of MFTF's `MagentoWebDriver` where
it overrides an assertion.

- Content: `see`, `dontSee`, `seeInSource`, `dontSeeInSource`, `seeInPageSource`,
  `dontSeeInPageSource`
- Elements: `seeElement`, `dontSeeElement`, `seeElementInDOM`,
  `dontSeeElementInDOM`, `seeNumberOfElements`, `seeNumberOfElementsInDOM`
- Fields and options: `seeInField`, `dontSeeInField`, `seeInFormFields`,
  `dontSeeInFormFields`, `seeCheckboxIsChecked`, `dontSeeCheckboxIsChecked`,
  `seeOptionIsSelected`, `dontSeeOptionIsSelected`
- Links and the title: `seeLink`, `dontSeeLink`, `seeInTitle`, `dontSeeInTitle`
- The URL: `seeInCurrentUrl`, `dontSeeInCurrentUrl`, `seeCurrentUrlEquals`,
  `dontSeeCurrentUrlEquals`, `seeCurrentUrlMatches`, `dontSeeCurrentUrlMatches`

An assertion that holds returns at once. One that fails is checked again when the
page changes, as the waits are, until it holds or `assertion_timeout` ends, 5 s
by default, and then fails with its last failure, unchanged. A `dontSee` waits for
what it should not see to go. `canSee` and the other conditional assertions retry
too, so one that fails takes the timeout. `assertion_timeout: 0` checks once.

```yaml
        - \Rnscommerce\Codeception\WebDriverBiDi\WebDriverBiDi:
              assertion_timeout: 5
```

Each check finds its elements as the WebDriver module does, so with its `wait`
above 0, a check of an element not there waits that long before it fails.
Assertions on popups, tabs and cookies, and the `grab` actions, are left to the
WebDriver module and checked once.

A suite's own assertion retries by passing it to `_retryAssertion` from a module
listed after this one, which finds each module by its name as the suite lists it.
For MFTF's `seeInSecretField`:

```php
public function seeInSecretField(string $field, string $value): void
{
    $magento = $this->getModule('\\' . MagentoWebDriver::class);
    $this->getModule('\\' . WebDriverBiDi::class)->_retryAssertion(
        static fn () => $magento->seeInSecretField($field, $value),
    );
}
```

## What it needs from the driver

A driver that speaks WebDriver BiDi. A session without it, such as one a helper
starts with capabilities of its own or with `_restart` and a new config, goes on
without BiDi: actions are not checked, assertions are checked once,
`waitForPageReady` returns at once, and the seven waits are the WebDriver
module's own.

It has been tested locally with ChromeDriver 156 and geckodriver 0.37 with
Firefox 158. CI runs against whichever drivers its runner image carries.

## Development

```sh
composer install
composer check
CHROMEDRIVER=… CHROME_BINARY=… GECKODRIVER=… composer test:integration
```

`composer test:integration` runs the Codeception project in `tests/Integration/Project`
against headless Chrome and Firefox, skipping a browser whose driver is not named.
`CHROME_BINARY` is optional.
