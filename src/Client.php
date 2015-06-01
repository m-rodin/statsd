<?php

namespace League\StatsD;

use League\StatsD\Exception\ConnectionException;
use League\StatsD\Exception\ConfigurationException;

/**
 * StatsD Client Class
 *
 * @author Marc Qualie <marc@marcqualie.com>
 */
class Client
{

    /**
     * Instance instances array
     * @var array
     */
    protected static $instances = array();


    /**
     * Instance ID
     * @var string
     */
    protected $instance_id;


    /**
     * Server Host
     * @var string
     */
    protected $host = '127.0.0.1';


    /**
     * Server Port
     * @var integer
     */
    protected $port = 8125;


    /**
     * Last message sent to the server
     * @var string
     */
    protected $message = '';


    /**
     * Class namespace
     * @var string
     */
    protected $namespace = '';

    /**
     * Timeout for creating the socket connection
     * @var null|float
     */
    protected $timeout;


    /**
     * Singleton Reference
     * @param  string $name Instance name
     * @return Client Client instance
     */
    public static function instance($name = 'default')
    {
        if (! isset(self::$instances[$name])) {
            self::$instances[$name] = new static($name);
        }
        return self::$instances[$name];
    }


    /**
     * Create a new instance
     * @param string $instance_id
     * @return void
     */
    public function __construct($instance_id = null)
    {
        $this->instance_id = $instance_id ?: uniqid();

        if (empty($this->timeout)) {
            $this->timeout = ini_get('default_socket_timeout');
        }
    }


    /**
     * Get string value of instance
     * @return string String representation of this instance
     */
    public function __toString()
    {
        return 'StatsD\Client::[' . $this->instance_id . ']';
    }


    /**
     * Initialize Connection Details
     * @param array $options Configuration options
     * @return Client This instance
     * @throws ConfigurationException If port is invalid
     */
    public function configure(array $options = array())
    {
        if (isset($options['host'])) {
            $this->host = $options['host'];
        }
        if (isset($options['port'])) {
            $port = (int) $options['port'];
            if (! $port || !is_numeric($port) || $port > 65535) {
                throw new ConfigurationException($this, 'Port is out of range');
            }
            $this->port = $port;
        }
        if (isset($options['namespace'])) {
            $this->namespace = $options['namespace'];
        }
        if (isset($options['timeout'])) {
            $this->timeout = $options['timeout'];
        }
        return $this;
    }


    /**
     * Get Host
     * @return string Host
     */
    public function getHost()
    {
        return $this->host;
    }


    /**
     * Get Port
     * @return string Port
     */
    public function getPort()
    {
        return $this->port;
    }


    /**
     * Get Namespace
     * @return string Namespace
     */
    public function getNamespace()
    {
        return $this->namespace;
    }


    /**
     * Get Last Message
     * @return string Last message sent to server
     */
    public function getLastMessage()
    {
        return $this->message;
    }


    /**
     * Increment a metric
     * @param  string|array $metrics Metric(s) to increment
     * @param  int $delta Value to decrement the metric by
     * @param  int $sampleRate Sample rate of metric
     * @return Client This instance
     */
    public function increment($metrics, $delta = 1, $sampleRate = 1)
    {
        $metrics = (array) $metrics;
        $data = array();
        if ($sampleRate < 1) {
            foreach ($metrics as $metric) {
                if ((mt_rand() / mt_getrandmax()) <= $sampleRate) {
                    $data[$metric] = $delta . '|c|@' . $sampleRate;
                }
            }
        } else {
            foreach ($metrics as $metric) {
                $data[$metric] = $delta . '|c';
            }
        }
        return $this->send($data);
    }


    /**
     * Increment a metric with tags
     * @param  string|array $metrics Metric(s) to increment
     * @param  array $tags Tags associated with metric(s)
     * @param  int $delta Value to decrement the metric by
     * @param  int $sampleRate Sample rate of metric
     * @return Client This instance
     */
    public function incrementTagged($metrics, $tags = [], $delta = 1, $sampleRate = 1)
    {
        $metricNames = [];
        foreach ((array)$metrics as $metric) {
            $metricNames[] = $this->_generateName($metric, $tags);
        }

        return $this->increment($metricNames, $delta, $sampleRate);
    }


    /**
     * Decrement a metric
     * @param  string|array $metrics Metric(s) to decrement
     * @param  int $delta Value to increment the metric by
     * @param  int $sampleRate Sample rate of metric
     * @return Client This instance
     */
    public function decrement($metrics, $delta = 1, $sampleRate = 1)
    {
        return $this->increment($metrics, 0 - $delta, $sampleRate);
    }


    /**
     * Decrement a metric with tags
     * @param  string|array $metrics Metric(s) to decrement
     * @param  array $tags Tags associated with metric(s)
     * @param  int $delta Value to decrement the metric by
     * @param  int $sampleRate Sample rate of metric
     * @return Client This instance
     */
    public function decrementTagged($metrics, $tags = [], $delta = 1, $sampleRate = 1)
    {
        $metricNames = [];
        foreach ((array)$metrics as $metric) {
            $metricNames[] = $this->_generateName($metric, $tags);
        }

        return $this->decrement($metricNames, $delta, $sampleRate);
    }


    /**
     * Timing
     * @param  string $metric Metric to track
     * @param  float $time Time in milliseconds
     * @param  array $tags Tags associated with metric
     * @return bool True if data transfer is successful
     */
    public function timing($metric, $time, $tags = [])
    {
        $taggedMetric = $this->_generateName($metric, $tags);

        return $this->send(
            array(
                $taggedMetric => $time . '|ms'
            )
        );
    }


    /**
     * Time a function
     * @param  string $metric Metric to time
     * @param  callable $func Function to record
     * @param  array $tags Tags associated with metric
     * @return bool True if data transfer is successful
     */
    public function time($metric, $func, $tags = [])
    {
        $timer_start = microtime(true);
        $func();
        $timer_end = microtime(true);
        $time = round(($timer_end - $timer_start) * 1000, 4);
        return $this->timing($metric, $time, $tags);
    }


    /**
     * Gauges
     * @param  string $metric Metric to gauge
     * @param  int $value Set the value of the gauge
     * @param  array $tags Tags associated with metric
     * @return Client This instance
     */
    public function gauge($metric, $value, $tags = [])
    {
        $taggedMetric = $this->_generateName($metric, $tags);

        return $this->send(
            array(
                $taggedMetric => $value . '|g'
            )
        );
    }

    /**
     * Sets - count the number of unique values passed to a key
     * @param $metric
     * @param mixed $value
     * @param array $tags Tags associated with metric
     * @return Client This instance
     */
    public function set($metric, $value, $tags = [])
    {
       $taggedMetric = $this->_generateName($metric, $tags);

       return $this->send(
            array(
                $taggedMetric => $value . '|s'
            )
        );
    }


    /**
     * Encode tags in metric name
     * @param $metric
     * @param array $tags
     * @return string
     */
    protected function _generateName($metric, $tags)
    {
        $resultName = $metric;

        foreach ($tags as $tag => $value) {
            $resultName .= "." . $tag . "=" . $value;
        }

        return $resultName;
    }


    /**
     * Send Data to StatsD Server
     * @param  array $data A list of messages to send to the server
     * @return Client This instance
     * @throws ConnectionException If there is a connection problem with the host
     */
    protected function send(array $data)
    {

        $socket = @fsockopen('udp://' . $this->host, $this->port, $errno, $errstr, $this->timeout);
        if (! $socket) {
            throw new ConnectionException($this, '(' . $errno . ') ' . $errstr);
        }
        $this->messages = array();
        $prefix = $this->namespace ? $this->namespace . '.' : '';
        foreach ($data as $key => $value) {
            $this->messages[] = $prefix . $key . ':' . $value;
        }
        $this->message = implode("\n", $this->messages);
        @fwrite($socket, $this->message);
        fclose($socket);
        return $this;

    }
}
