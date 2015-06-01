<?php

namespace League\StatsD\Test;

class CounterTaggedTest extends TestCase
{

    public function testIncrementTagged()
    {
        $this->client->incrementTagged('test_metric', ['foo' => 'bar', 'mam' => 'bo']);
        $this->assertEquals('test_metric._t_foo.bar._t_mam.bo:1|c', $this->client->getLastMessage());
    }


    public function testIncrementArrayTagged()
    {
        $this->client->incrementTagged(['test_metric.one', 'test_metric.two'], ['foo' => 'bar', 'mam' => 'bo']);
        $this->assertEquals("test_metric.one._t_foo.bar._t_mam.bo:1|c\ntest_metric.two._t_foo.bar._t_mam.bo:1|c", $this->client->getLastMessage());
    }


    public function testDecrementTagged()
    {
        $this->client->decrementTagged('test_metric', ['foo' => 'bar', 'mam' => 'bo']);
        $this->assertEquals('test_metric._t_foo.bar._t_mam.bo:-1|c', $this->client->getLastMessage());
    }


    public function testDecrementArrayTagged()
    {
        $this->client->decrementTagged(['test_metric.one', 'test_metric.two'], ['foo' => 'bar', 'mam' => 'bo']);
        $this->assertEquals("test_metric.one._t_foo.bar._t_mam.bo:-1|c\ntest_metric.two._t_foo.bar._t_mam.bo:-1|c", $this->client->getLastMessage());
    }

}
