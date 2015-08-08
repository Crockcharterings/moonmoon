<?php

class FoolCategory {
    var $name = null;
    function __construct($name) { $this->name = $name; }
    function get_label() { return $this->name; }
}

class FoolItem
{
    function __construct($categories)
    {
        foreach ($categories as $c)
            $this->categories[] = new FoolCategory($c);
    }

    function get_categories() { return $this->categories; }
}

class PlanetTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        $this->planet = new Planet();

        $this->items = array(
            new FoolItem(array('catA', 'catB', 'catC')),
            new FoolItem(array('catB')),
            new FoolItem(array('catA')),
            new FoolItem(array('catC'))
        );
    }

    protected function _after()
    {
        unset($this->planet);
    }

    public function testFilterItemsByCategoryWithInvalidCategory()
    {
        $this->assertEquals(count($this->planet->_filterItemsByCategory($this->items, null)), count($this->items));
        $this->assertEquals(count($this->planet->_filterItemsByCategory($this->items, '')), count($this->items));
    }

    public function testFilterItemsByCategoryWithNonUsedCategory()
    {
        $this->assertEquals(count($this->planet->_filterItemsByCategory($this->items, 'catD')), 0);
    }

    public function testFilterItemsByCategoryWithValidCategory()
    {
        $this->assertEquals(count($this->planet->_filterItemsByCategory($this->items, 'catA')), 2);
        $this->assertEquals(count($this->planet->_filterItemsByCategory($this->items, 'catB')), 2);
        $this->assertEquals(count($this->planet->_filterItemsByCategory($this->items, 'catC')), 2);
    }

    public function testFilterItemsByCategoryWithMultipleCategory()
    {
        $this->assertEquals(count($this->planet->_filterItemsByCategory($this->items, 'catA,catB')), 3);
    }


}
