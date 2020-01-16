<?php

namespace Kelemen\SimpleMapper\Tests;

require_once 'Task/FirstTask.php';

use Parallel\TaskStack\StackedTask;
use Parallel\TaskStack\TaskStack;
use PHPUnit\Framework\TestCase;
use Tests\FirstTask;

class TaskStackTest extends TestCase
{
   public function testStackedTasks()
   {
       $taskStack = new TaskStack();
       $taskStack->addTask(new FirstTask('task:first'));

       $stackedTasks = $taskStack->getStackedTasks();

       $this->assertArrayHasKey('task:first', $stackedTasks);

       foreach ($stackedTasks as $stackedTaskName => $stackedTask) {
            $this->assertInstanceOf(StackedTask::class, $stackedTask);
       }
   }
}