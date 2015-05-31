<?php

namespace League\StatsD\Test;

class SetTest extends TestCase
{

    public function testSet()
    {
        $this->client->set('test_metric', 456);
        $this->assertEquals('test_metric:456|s', $this->client->getLastMessage());
    }


    public function testSetTagged()
    {
        $this->client->set('test_metric', 456, ['foo' => 'bar', 'mam' => 'bo']);
        $this->assertEquals('test_metric.foo=bar.mam=bo:456|s', $this->client->getLastMessage());
    }

}
