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
     */
    public function __construct(
        ClientInterface $client,
        int $pageIndex = null,
        int $limit = null,
        array $query = [],
        bool $cache = true
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

        if ($count === FALSE) {
          $this->count = count($this->pages);
        }

        $this->pages[$this->position]->rewind();
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
        if ($this->count !== NULL && $this->position > $this->count) {
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
}
