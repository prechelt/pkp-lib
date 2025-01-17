<?php

/**
 * @file classes/cache/MemcacheCache.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MemcacheCache
 * @ingroup cache
 *
 * @see GenericCache
 *
 * @brief Provides caching based on Memcache.
 */

namespace PKP\cache;

// WARNING: This cache MUST be loaded in batch, or else many cache
// misses will result.

// Pseudotypes used to represent false and null values in the cache
class memcache_false
{
}
class memcache_null
{
}

class MemcacheCache extends GenericCache
{
    /**
     * Connection to use for caching.
     */
    public $connection;

    /**
     * Flag (used by Memcache::set)
     */
    public $flag;

    /**
     * Expiry (used by Memcache::set)
     */
    public $expire;

    /**
     * Instantiate a cache.
     */
    public function __construct($context, $cacheId, $fallback, $hostname, $port)
    {
        parent::__construct($context, $cacheId, $fallback);
        $this->connection = new Memcached();

        // FIXME This should use connection pooling
        // XXX check whether memcached server is usable
        if (!$this->connection->addServer($hostname, $port)) {
            $this->connection = null;
        }

        $this->flag = null;
        $this->expire = 3600; // 1 hour default expiry
    }

    /**
     * Set the flag (used in Memcache::set)
     */
    public function setFlag($flag)
    {
        $this->flag = $flag;
    }

    /**
     * Set the expiry time (used in Memcache::set)
     */
    public function setExpiry($expiry)
    {
        $this->expire = $expiry;
    }

    /**
     * Flush the cache.
     */
    public function flush()
    {
        $this->connection->flush();
    }

    /**
     * Get an object from the cache.
     *
     * @param string $id
     */
    public function getCache($id)
    {
        $result = $this->connection->get($this->getContext() . ':' . $this->getCacheId() . ':' . $id);
        if ($this->connection->getResultCode() == Memcached::RES_NOTFOUND) {
            return $this->cacheMiss;
        }
        if ($result instanceof memcache_false) {
            $result = false;
        }
        if ($result instanceof memcache_null) {
            $result = null;
        }
        return $result;
    }

    /**
     * Set an object in the cache. This function should be overridden
     * by subclasses.
     *
     * @param string $id
     */
    public function setCache($id, $value)
    {
        if ($value === false) {
            $value = new memcache_false();
        } elseif ($value === null) {
            $value = new memcache_null();
        }
        return ($this->connection->set($this->getContext() . ':' . $this->getCacheId() . ':' . $id, $value, $this->expire));
    }

    /**
     * Close the cache and free resources.
     */
    public function close()
    {
        $this->connection->quit();
        unset($this->connection);
        $this->contextChecked = false;
    }

    /**
     * Get the time at which the data was cached.
     * Note that keys expire in memcache, which means
     * that it's possible that the date will disappear
     * before the data -- in this case we'll have to
     * assume the data is still good.
     */
    public function getCacheTime()
    {
        return null;
    }

    /**
     * Set the entire contents of the cache.
     * WARNING: THIS DOES NOT FLUSH THE CACHE FIRST!
     * This is because there is no "scope restriction"
     * for flushing within memcache and therefore
     * a flush here would flush the entire cache,
     * resulting in more subsequent calls to this function,
     * resulting in more flushes, etc.
     */
    public function setEntireCache($contents)
    {
        foreach ($contents as $id => $value) {
            $this->setCache($id, $value);
        }
    }
}
