<?php

namespace KrystalCode\ApiIterator;

/**
 * Defines the interface for API clients.
 *
 * Normally you would need to implement an adapter for the PHP SDK or other
 * library that makes the actual calls to the API endpoints so that it
 * implements this interface. This is necessary for the iterator to know how to
 * make the API calls for getting the results.
 */
interface ClientInterface
{
    /**
     * Gets the list of items from the API resource.
     *
     * @param array $options
     *   An associative array of options. Supported options are:
     *   - page (int): The index of the page to get, if the resource supports
     *     paging.
     *   - limit (int): The number of items to get.
     *   - bypass_iterator (bool): The items are normally returned wrapped in an
     *     API iterator. When the `bypass_iterator` option is set to, the items
     *     should be  returned without that extra wrapper iterator i.e. in just
     *     a `\CachingIterator`.
     * @param array $query
     *   An associative array containing additional query parameters to add to
     *   the request.
     *
     * @return \KrystalCode\ApiIterator\IteratorInterface|array|\CachingIterator
     *   - If the endpoint supports paging, an API iterator containing the
     *     items.
     *   - If the `bypass_iterator` option is set to `true`, an array containing
     *     the following elements in the given order.
     *     - \CachingIterator: An iterator containing the list items; items are
     *       `stdClass` objects.
     *     - int|null|false: The total number of pages, NULL if unknown, or
     *       FALSE if unknown but we know that we have reached the last page.
     *     - array: The updated query array. This may be used to update the
     *       token or the URL that will be used to get the next page.
     *   - If the endpoint does not support paging, a `\CachingIterator`
     *     iterator containing the list items.
     *
     * @throws \InvalidArgumentException
     *   If options related to paging are set but the resource does not support
     *   paging.
     */
    public function list(array $options = [], array $query = []);

}
