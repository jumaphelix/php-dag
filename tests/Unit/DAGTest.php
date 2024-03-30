<?php

namespace JumaPhelix\DAG\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JumaPhelix\DAG\DAG;
use JumaPhelix\DAG\Task;

class DAGTest extends TestCase {

    public function _testTaskSortingAndDependencies() {

        $dag = new DAG();

        // Create tasks as instances or mocks of Task
        $taskA = new Task('A', function() {});
        $taskB = new Task('B', function() {});
        $taskC = new Task('C', function() {});
        $taskD = new Task('D', function() {});
        $taskE = new Task('E', function() {});

        // Add tasks to the DAG
        $dag->addTask($taskA);
        $dag->addTask($taskB);
        $dag->addTask($taskC);
        $dag->addTask($taskD);
        $dag->addTask($taskE);

        // Define dependencies (C, D, A, B)
        $dag->addParent('A', 'D');
        $dag->addParent('B', 'E');
        $dag->addParent('A', 'B');

        // Test that dependencies are correctly identified
        //$this->assertEquals(['A'], $dag->getChildren('D'), 'Task A should depend on Task D.');
        //$this->assertEquals(['B'], $dag->getChildren('A'), 'Task B should depend on Task C');

        // Perform a topological sort and test the order
        $sortedTasks = $dag->topologicalSort();

        print "\nSorted Tasks\n";
        print_r($sortedTasks);

        $expectedOrder = ['C', 'D', 'A', 'B']; // One possible valid order

        $this->assertEquals($expectedOrder, $sortedTasks);
    }
}
