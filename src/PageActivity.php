<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

/**
 * The network and DOM activity of each window, iframes counted with the window they are in, and when it is quiet.
 *
 * Times are in seconds, from any clock the caller uses throughout.
 */
final class PageActivity
{
    public const NETWORK_QUIET = 0.1;

    public const DOM_QUIET = 0.1;

    /** How long the network alone must be quiet when the DOM never stops changing. */
    public const DOM_BUSY_CAP = 1.0;

    /** How old a request in flight may be before it no longer counts, such as a long poll. */
    public const STALE_REQUEST = 10.0;

    /** @var array<string, string> each browsing context's window */
    private array $windows = [];

    /**
     * @var array<string, array{string, float, string, ?string}> each request in flight: its window, when it was sent,
     *     its browsing context and the navigation it is for
     */
    private array $inFlight = [];

    /** @var array<string, float> */
    private array $lastNetwork = [];

    /** @var array<string, float> */
    private array $lastDom = [];

    private int $recorded = 0;

    public function contextCreated(string $context, ?string $parent): void
    {
        $this->windows[$context] = $parent === null ? $context : $this->windowOf($parent);
    }

    public function requestSent(string $request, string $context, float $at, ?string $navigation = null): void
    {
        $window = $this->windowOf($context);
        $this->inFlight[$request] = [$window, $at, $context, $navigation];
        $this->lastNetwork[$window] = $at;
        $this->recorded++;
    }

    /**
     * Forgets the requests of the document a navigation replaces, and of a window's iframes when the window navigates,
     * which some browsers never say have ended.
     */
    public function navigationStarted(string $context, ?string $navigation): void
    {
        $navigatesWindow = $this->windowOf($context) === $context;
        foreach ($this->inFlight as $request => [$window, , $requestContext, $requestNavigation]) {
            $replaced = $navigatesWindow ? $window === $context : $requestContext === $context;
            if ($replaced && ($navigation === null || $requestNavigation !== $navigation)) {
                unset($this->inFlight[$request]);
            }
        }
        $this->recorded++;
    }

    public function requestEnded(string $request, string $context, float $at): void
    {
        unset($this->inFlight[$request]);
        $this->lastNetwork[$this->windowOf($context)] = $at;
        $this->recorded++;
    }

    public function domChanged(string $context, float $at): void
    {
        $this->lastDom[$this->windowOf($context)] = $at;
        $this->recorded++;
    }

    /**
     * How many requests, DOM changes and navigations have been recorded, which changes whenever quietIn may have.
     */
    public function recorded(): int
    {
        return $this->recorded;
    }

    /**
     * Seconds until the window could be quiet if nothing more happened, or 0 when it is quiet now: no request in
     * flight and the network quiet, and the DOM quiet or the network quiet long enough to stop waiting for it.
     * Quiet counts from the last activity, except that the network's quiet ends the wait for the DOM only from
     * $since on.
     */
    public function quietIn(string $window, float $since, float $now): float
    {
        $staleIn = null;
        foreach ($this->inFlight as [$requestWindow, $sentAt]) {
            $left = $sentAt + self::STALE_REQUEST - $now;
            if ($requestWindow === $window && $left > 0) {
                $staleIn = min($staleIn ?? $left, $left);
            }
        }
        if ($staleIn !== null) {
            return $staleIn;
        }
        $networkQuietFor = $now - ($this->lastNetwork[$window] ?? -INF);
        $domQuietFor = $now - ($this->lastDom[$window] ?? -INF);
        $quietIn = max(self::NETWORK_QUIET - $networkQuietFor, self::DOM_QUIET - $domQuietFor);
        $domWaitedFor = $now - max($this->lastNetwork[$window] ?? $since, $since);
        return max(0.0, min($quietIn, self::DOM_BUSY_CAP - $domWaitedFor));
    }

    private function windowOf(string $context): string
    {
        return $this->windows[$context] ?? $context;
    }
}
