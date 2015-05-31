<?php

namespace League\StatsD\Test;

class GaugeTest extends TestCase
{

    public function testGauge()
    {
        $this->client->gauge('test_metric', 456);
        $this->assertEquals('test_metric:456|g', $this->client->getLastMessage());
    }


    public function testGaugeTagged()
    {
        $this->client->gauge('test_metric', 456, ['foo' => 'bar', 'mam' => 'bo']);
        $this->assertEquals('test_metric.foo=bar.mam=bo:456|g', $this->client->getLastMessage());
    }

}
