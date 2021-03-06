<?php

/*
 * This file is part of the Stash package.
 *
 * (c) Robert Hafner <tedivm@tedivm.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stash\Driver;

use Stash;
use Stash\Exception\RuntimeException;
use Stash\Interfaces\DriverInterface;

/**
 * Composite is a wrapper around one or more StashDrivers, allowing faster caching engines with size or
 * persistence limitations to be backed up by slower but larger and more persistent caches. There are no artificial
 * limits placed on how many drivers can be staggered.
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Composite implements DriverInterface
{

    protected $drivers = array();

    /**
     * This function should takes an array which is used to pass option values to the driver.
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        if (!isset($options['drivers']) || !is_array($options['drivers']) || count($options['drivers']) < 1) {
            throw new RuntimeException('One or more secondary drivers are required.');
        }

        foreach ($options['drivers'] as $driver) {
            if (!(is_object($driver) && $driver instanceof DriverInterface)) {
                continue;
            }

            $this->drivers[] = $driver;
        }

        if (count($this->drivers) < 1) {
            throw new RuntimeException('None of the secondary drivers can be enabled.');
        }
    }

    /**
     * Empty destructor to maintain a standardized interface across all drivers.
     *
     */
    public function __destruct()
    {
    }

    /**
     * This function should return the data array, exactly as it was received by the storeData function, or false if it
     * is not present. This array should have a value for "data" and for "expiration", which should be the data the
     * main script is trying to store.
     *
     * @param $key
     * @return array
     */
    public function getData($key)
    {
        $failedDrivers = array();
        $return = false;
        foreach ($this->drivers as $driver) {
            if ($return = $driver->getData($key)) {
                $failedDrivers = array_reverse($failedDrivers);
                foreach ($failedDrivers as $failedDriver) {
                    $failedDriver->storeData($key, $return['data'], $return['expiration']);
                }

                break;
            } else {
                $failedDrivers[] = $driver;
            }
        }

        return $return;
    }

    /**
     *
     * @param array $key
     * @param array $data
     * @param int   $expiration
     *
     * @return bool
     */
    public function storeData($key, $data, $expiration)
    {
        return $this->actOnAll('storeData', array($key, $data, $expiration));
    }

    /**
     * This function should clear the cache tree using the key array provided. If called with no arguments the entire
     * cache needs to be cleared.
     *
     * @param  null|array $key
     * @return bool
     */
    public function clear($key = null)
    {
        return $this->actOnAll('clear', array($key));
    }

    /**
     * This function is used to remove expired items from the cache.
     *
     * @return bool
     */
    public function purge()
    {
        return $this->actOnAll('purge');
    }

    protected function actOnAll($action, $args = array())
    {
        $drivers = array_reverse($this->drivers);
        $return = true;
        $results = false;
        foreach ($drivers as $driver) {
            switch ($action) {
                case 'purge':
                    $results = $driver->purge();
                    break;
                case 'clear':
                    $results = $driver->clear($args[0]);
                    break;
                case 'storeData':
                    $results = $driver->storeData($args[0], $args[1], $args[2]);
                    break;
            }
            $return = $return && $results;
        }

        return $return;
    }

    /**
     * This function checks to see if this driver is available. This always returns true because this
     * driver has no dependencies, begin a wrapper around other classes.
     *
     * @return bool true
     */
    public static function isAvailable()
    {
        return true;
    }
}
