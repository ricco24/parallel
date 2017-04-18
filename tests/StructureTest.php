<?php

namespace Kelemen\SimpleMapper\Tests;

require_once 'TestBase.php';

use SimpleMapper\ActiveRow;
use SimpleMapper\CustomStructure;
use SimpleMapper\Selection;

class StructureTest extends TestBase
{
   public function testCustomStructure()
   {
       $structure = new CustomStructure();
       $structure->addActiveRowClass('table1', 'row class table 1');
       $structure->addActiveRowClass('table2', 'row class table 2');
       $structure->addSelectionClass('table1', 'select class table 1');
       $structure->addSelectionClass('table2', 'select class table 2');

       $this->assertEquals('row class table 1', $structure->getActiveRowClass('table1'));
       $this->assertEquals('row class table 2', $structure->getActiveRowClass('table2'));
       $this->assertEquals('select class table 1', $structure->getSelectionClass('table1'));
       $this->assertEquals('select class table 2', $structure->getSelectionClass('table2'));

       $this->assertEquals(ActiveRow::class, $structure->getActiveRowClass('table3'));
       $this->assertEquals(Selection::class, $structure->getSelectionClass('table3'));
   }

    public function testDefaultsCustomStructure()
    {
        $structure = new CustomStructure();
        $this->assertEquals(ActiveRow::class, $structure->getActiveRowClass('table3'));
        $this->assertEquals(Selection::class, $structure->getSelectionClass('table3'));
    }

    public function testFluentCustomStructure()
    {
        $structure = new CustomStructure();
        $structure
            ->addActiveRowClass('table1', 'row class table 1')
            ->addActiveRowClass('table2', 'row class table 2')
            ->addSelectionClass('table1', 'select class table 1')
            ->addSelectionClass('table2', 'select class table 2');
    }
}