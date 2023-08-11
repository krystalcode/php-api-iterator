<?php

namespace KrystalCode\ApiIterator;

/**
 * Defines the interface for API iterators.
 *
 * API iterators facilitate getting results from resource endpoints that list
 * items with support for pagination. It also supports caching i.e. browsing
 * pages that are already retrieved without making new API calls.
 */
interface IteratorInterface extends \Iterator
{
    /**
     * Sets the current position i.e. page index of the iterator.
     *
     * @param int $pageIndex
     *   The page index to set the current position to.
     */
    public function setKey(int $pageIndex): void;

    /**
     * Gets the total number of pages for the iterator.
     *
     * The total number of pages is generally not known before getting the items
     * for at least one page from the API. Other APIs do not provide the total
     * number of pages in the response; instead they provide a token or a link
     * to the next page, or they provide just the results and the client has to
     * browse them in pages until no results are returned. In such cases we do
     * not know the total number of pages until we iterate through all of them.
     * This method returns `null` until the count is known.
     *
     * @return int|null
     *   The total number of pages, or `null` if it is not known yet.
     */
    public function count(): ?int;

    /**
     * Sets the total number of pages for the iterator.
     *
     * @param int $nbPages
     *   The total number of pages.
     */
    public function setCount(int $nbPages): void;

    /**
     * Gets whether caching is enabled or not.
     *
     * @return bool
     *   True if caching is enabled, false otherwise.
     */
    public function cache(): bool;

    /**
     * Enables or disables caching.
     *
     * @param bool $cache
     *   True for enabling caching, false for disabling.
     */
    public function setCache(bool $cache): void;

    /**
     * Gets the items for the requested page.
     *
     * It moves the position to the requested page index and fetches the
     * items.
     *
     * @param int $pageIndex
     *   The index of the page for which to get the results.
     *
     * @return \CachingIterator
     *   An iterator containing the items.
     *
     * @throws \InvalidArgumentException
     *   If the given page index is an invalid position.
     */
    public function get(int $pageIndex): \CachingIterator;

    /**
     * Moves the position to the requested page.
     *
     * @param int $pageIndex
     *   The index of the page to move the position to.
     */
    public function move(int $pageIndex): void;

    /**
     * Gets an array containing all items in a single-dimensional array.
     *
     * Iterates through all pages collecting their items (either from the
     * resource or from the cache) and returns all items in an array.
     *
     * It moves the position to the first page at the beginning to ensure items
     * from all pages are collected. It does not keep track of the position
     * before the call; instead, it rewinds at the end.
     *
     * @return array
     *   An array containing all items from all pages.
     */
    public function getAllItems();
}
