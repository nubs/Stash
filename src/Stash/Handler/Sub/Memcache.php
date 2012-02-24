<?php

/*
 * This file is part of the Stash package.
 *
 * (c) Robert Hafner <tedivm@tedivm.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stash\Handler\Sub;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Memcache extends Memcached
{
    public function initialize($servers, array $options = array())
    {
        $memcache = new \Memcache();

        foreach ($servers as $server) {
            $host = $server[0];
            $port = isset($server[1]) ? $server[1] : 11211;
            $weight = isset($server[2]) ? (int)$server[2] : null;

            if (is_integer($weight)) {
                $memcache->addServer($host, $port, true, $weight);
            } else {
                $memcache->addServer($host, $port);
            }
        }

        $this->memcached = $memcache;
    }

    public function set($key, $value, $expire = null)
    {
        return $this->memcached->set($key, array('data' => $value, 'expiration' => $expire), null, $expire);
    }

    public function get($key)
    {
        return @$this->memcached->get($key);
    }

    public function cas($key, $value)
    {
        if (($return = @$this->memcached->get($key)) !== false) {
            return $return;
        }

        $this->memcached->set($key, $value);
        return $value;
    }

    public function canEnable()
    {
        return class_exists('Memcache', false);
    }
}