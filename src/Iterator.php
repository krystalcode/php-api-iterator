<?php

namespace KrystalCode\ApiIterator;

/**
 * Default implementation of the API iterator.
 *
 * @I Support alternative cache backends
 *    type     : improvement
 *    priority : normal
 *    labels   : cache
 * @I Support offset
 *    type     : improvement
 *    priority : normal
 *    labels   : iterator
 * @I Support retries on throwables when calling `list` on the client
 *    type     : feature
 *    priority : normal
 *    labels   : error-handling, iterator
 */
class Iterator implements IteratorInterface
{
    /**
     * Whether to use cached results, when available.
     *
     * Results fetched are stored in the iterator object and do not persist
     * across iterators.
     *
     * @var bool
     */
    protected $cache;

    /**
     * The seconds/nanoseconds to sleep after requesting a page.
     *
     * Some API have rate limits that could be hit when iterating over a large
     * number of pages without a delay between page requests.
     *
     * For example:
     * - API has a limit of 10 requests per second.
     * - There's 50 pages for the query.
     * - API responds to each request fast e.g. in milliseconds.
     * - There's no delay between requesting the next page i.e. processing the
     *   results is also fast.
     *
     * In such cases, looping over the iterator will result in hitting the API
     * rate limits and some of the pages failing to be fetched.
     *
     * Providing a delay will instruct the iterator to sleep after fetching a
     * page for that amount of time. For example, in the case above the delay
     * could be set to 0 seconds and 100000000 nanoseconds (i.e. 0.1 seconds)
     * which will ensure that the 10 requests per second will not be exceeded
     * regardless of response and processing times.
     *
     * The delay must be given as an array containing the number of seconds in
     * its first element and the number of nanoseconds in its second element.
     *
     * In the example above that would be [0, 100000000];
     *
     * @var array
     *
     * @see time_nanosleep()
     */
    protected $delay;

    /**
     * The client that will be used to make requests to the API.
     *
     * @var \KrystalCode\ApiIterator\ClientInterface
     */
    protected $client;

    /**
     * Holds the items fetched from the API, indexed by page index.
     *
     * @var \CachingIterator
     */
    protected $pages = [];

    /**
     * The total number of pages.
     *
     * @var int
     */
    protected $count;

    /**
     * The current iterator position i.e. the current page index.
     *
     * @var int
     */
    protected $position = 1;

    /**
     * The number of items to fetch per page.
     *
     * @var int
     */
    protected $limit = 100;

    /**
     * An associative array containing query parameters to add to the requests.
     *
     * @var array
     */
    protected $query = [];

    /**
     * Constructs a new Iterator object.
     *
     * @param \KrystalCode\ApiIterator\ClientInterface $client
     *   The client that will be used to make requests to the API.
     * @param int $pageIndex
     *   The index of the page to start the iterator at.
     * @param int $limit
     *   The number of items to fetch per page.
     * @param array $query
     *   An associative array containing additional query parameters to add to
     *   the requests.
     * @param bool $cache
     *   Whether to reuse cached results or not.
     * @param array $delay
     *   A pair of seconds/nanoseconds that will determine the delay after
     *   fetching a page.
     */
    public function __construct(
        ClientInterface $client,
        int $pageIndex = null,
        int $limit = null,
        array $query = [],
        bool $cache = true,
        array $delay = null
    ) {
        $this->client = $client;

        if ($pageIndex) {
            $this->position = $pageIndex;
        }
        if ($limit) {
            $this->limit = $limit;
        }

        $this->query = $query;
        $this->cache = $cache;

        if ($delay) {
            $this->delay = $delay;
            $this->validateDelay();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function current(): \CachingIterator
    {
        if ($this->cache && isset($this->pages[$this->position])) {
            $this->pages[$this->position]->rewind();
            return $this->pages[$this->position];
        }

        [
            $this->pages[$this->position],
            $count,
            $this->query
        ] = $this->client->list(
            [
                'bypass_iterator' => true,
                'page' => $this->position,
                'limit' => $this->limit,
            ],
            $this->query
        );

        // `false` means that we have reached the last page.
        if ($count === false) {
            $this->count = $this->position;
        }
        // If we have a number, we must have been given the total number of
        // pages or total count in the response. `null` means that we don't
        // know yet.
        elseif ($count !== null) {
            $this->count = $count;
        }

        $this->pages[$this->position]->rewind();

        if ($this->delay !== null) {
            time_nanosleep($this->delay[0], $this->delay[1]);
        }

        return $this->pages[$this->position];
    }

    /**
     * {@inheritdoc}
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function next(): void
    {
        // If we are not using cached results, let's remove them to reduce
        // memory consumption.
        // @I Write tests for whether items are removed when cache is disabled
        //    type     : task
        //    priority : low
        //    labels   : testing
        if (!$this->cache && $this->pages[$this->position]) {
            $this->pages[$this->position] = null;
        }

        ++$this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->position = 1;
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        if ($this->position < 1) {
            return false;
        }

        // We don't always know the total number of pages.
        // See the comments in the `count` method.
        if ($this->count !== null && $this->position > $this->count) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setKey(int $pageIndex): void
    {
        $this->position = $pageIndex;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): ?int
    {
        if ($this->count) {
            return $this->count;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function setCount(int $nbPages): void
    {
        $this->count = $nbPages;
    }

    /**
     * {@inheritdoc}
     */
    public function cache(): bool
    {
        return $this->cache;
    }

    /**
     * {@inheritdoc}
     */
    public function setCache(bool $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function get(int $pageIndex): \CachingIterator
    {
        $this->move($pageIndex);
        return $this->current();
    }

    /**
     * {@inheritdoc}
     */
    public function move(int $pageIndex): void
    {
        $this->position = $pageIndex;

        if (!$this->valid()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Page "%s" is either an invalid page index or it exceeds the total number of pages available.',
                    $pageIndex
                )
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAllItems()
    {
        $this->rewind();

        $items = [];
        while ($this->valid()) {
            foreach ($this->current() as $item) {
                $items[] = $item;
            }
            $this->next();
        }

        $this->rewind();
        return $items;
    }

    /**
     * Validates that the iterator delay is in the expected format.
     *
     * If set, it must be an array containing the seconds and nanoseconds as
     * integer numbers, as expected by time_nanosleep().
     *
     * @throws \InvalidArgumentException
     *   When the delay is set but in an incorrect format.
     */
    protected function validateDelay()
    {
        if (!isset($this->delay)) {
            return;
        }
        if (!isset($this->delay[0]) || !is_int($this->delay[0])) {
            throw new \InvalidArgumentException(
                'You must provide the seconds of the delay as an integer.'
            );
        }
        if (!isset($this->delay[1]) || !is_int($this->delay[1])) {
            throw new \InvalidArgumentException(
                'You must provide the nanoseconds of the delay as an integer.'
            );
        }
    }
}
