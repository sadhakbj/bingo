<?php

declare(strict_types = 1);

namespace Bingo\Discovery\Discoverers;

/**
 * Contract for discovery components.
 *
 * Discoverers scan specific parts of the application (controllers, commands,
 * middleware, etc.) and return structured metadata that can be cached.
 */
interface DiscovererInterface
{
    /**
     * Return the discovery type identifier.
     *
     * Used as the array key in the cache file (e.g., 'controllers', 'commands').
     *
     * @return string
     */
    public function type(): string;

    /**
     * Perform the discovery and return structured data.
     *
     * This method scans relevant directories, reads PHP attributes and class
     * metadata, and returns a structured array ready for caching.
     *
     * @return array
     */
    public function discover(): array;
}
