<?php

namespace JumaPhelix\DAG\Tests;

use JumaPhelix\DAG\DAG;
use JumaPhelix\DAG\SharedDataManager;
use JumaPhelix\DAG\Task;
use JumaPhelix\DAG\TaskExecutor;
use JumaPhelix\DAG\TaskStatus;
use PHPUnit\Framework\TestCase;

class TaskExecutorTest extends TestCase {

    public static function taskA($parentResults, $name) {

        sleep(1);

        $message = "";
        if (!empty($parentResults)) {
            foreach ($parentResults as $parentResult) {
                $message .= "($parentResult) ";
            }
        }
        $message .= "Task A completed in 1 second for $name";

        return $message;
    }

    public function testTaskExecutionTime() {

        $data = [];

        // Set up the tasks as DAG
        $dag = new DAG();
        $dataManager = new SharedDataManager($data);

        // Create tasks as instances or mocks of Task
        $taskA = new Task('A', function ($parentResults = null) use($dataManager) {

            $args = ['name' => "Phelix"];

            $result = self::taskA($parentResults, ...$args);

            // Modify the shared data in a lock-safe way
            $dataManager->modifyData(function($data) use($result) {
                $data['A'] = $result;
                return $data;
            });

            return $result;

        });

        $taskB = new Task('B', function($parentResults = null) use($dataManager)  {

            sleep(2);

            $message = "";
            if (!empty($parentResults)) {
                foreach ($parentResults as $parentResult) {
                    $message .= "($parentResult) ";
                }
            }
            $message .= "Task B completed in 2 seconds";

            // Modify the shared data in a lock-safe way
            $dataManager->modifyData(function($data) use($message) {
                $data['B'] = $message;
                return $data;
            });

            return $message;

        });
        $taskC = new Task('C', function($parentResults = null) use($dataManager) {

            sleep(1);

            $message = "";
            if (!empty($parentResults)) {
                foreach ($parentResults as $parentResult) {
                    $message .= "($parentResult) ";
                }
            }
            $message .= "Task C completed in 1 second";

            // Modify the shared data in a lock-safe way
            $dataManager->modifyData(function($data) use($message) {
                $data['C'] = $message;
                return $data;
            });

            return $message;

        });
        $taskD = new Task('D', function($parentResults = null) use($dataManager) {

            sleep(3);

            $message = "";
            if (!empty($parentResults)) {
                foreach ($parentResults as $parentResult) {
                    $message .= "($parentResult) ";
                }
            }
            $message .= "Task D completed in 3 seconds";

            // Modify the shared data in a lock-safe way
            $dataManager->modifyData(function($data) use($message) {
                $data['D'] = $message;
                return $data;
            });

            return $message;

        });
        $taskE = new Task('E', function($parentResults = null) use($dataManager) {

            sleep(3);

            $message = "";
            if (!empty($parentResults)) {
                foreach ($parentResults as $parentResult) {
                    $message .= "($parentResult) ";
                }
            }
            $message .= "Task E completed in 3 seconds";

            // Modify the shared data in a lock-safe way
            $dataManager->modifyData(function($data) use($message) {
                $data['E'] = $message;
                return $data;
            });

            return $message;
        });

        // Add tasks to the DAG
        $dag->addTask($taskA);
        $dag->addTask($taskB);
        $dag->addTask($taskC);
        $dag->addTask($taskD);
        $dag->addTask($taskE);

        // Define dependencies (C, D, E, A, B)
        $dag->addParent('A', 'D');
        $dag->addParent('D', 'C');
        $dag->addParent('C', 'A');

        print "\nVisualize Tasks\n";
        print_r($dag->visualize());

        // Initialize the task executor
        $executor = new TaskExecutor($dag);

        // Execute tasks
        $executor->execute();

        $executionTime = $executor->getExecutionTime();
        print "\nAll tasks executed in  $executionTime seconds\n";

        $allResults = $executor->getResults();
        $lastResult = $executor->getFinalResult();

        //print "\nAll Results\n";
        foreach ($allResults as $result) {
            $message = "Task {$result->getId()} status is {$result->getStatus()}. Executed in {$result->getExecutionTime()} seconds.";
            if ($result->getStatus() == TaskStatus::FAILED) {
                $message .= " Error says {$result->getException()}.";
            } elseif ($result->getStatus() == TaskStatus::COMPLETED) {
                $message .= " Response is ".json_encode($result->getResult());
            }
            print "\n$message\n";
        }

        print "\nFinal Result\n";
        print_r($lastResult->getExecutionTime());

        print "\nShared Data\n";
        print_r($dataManager->getData());
        //print_r($executor->getTaskResults());

        print "\nAll tasks completed in {$executor->getExecutionTime()} seconds \n";

        // Assert that all tasks completed within the expected time frame
        // Since tasks A and B can run in parallel, and C waits for A, the total time should be close to 3 seconds
        $this->assertLessThan(4, $executionTime, "Tasks took longer than expected to execute.");

        // Assert task results
        $this->assertEquals('Task A completed', $dag->getTask('A')->getResult());
        $this->assertEquals('Task B completed', $dag->getTask('B')->getResult());
        $this->assertEquals('Task A completed; Task C completed', $dag->getTask('C')->getResult());
    }

    public function _testLoop() {

        $data = [];

        // Set up the tasks as DAG
        $dag = new DAG();
        $dataManager = new SharedDataManager($data);

        $count = 10000;

        for ($i = 0; $i < $count; $i++) {

            $dag->addTask(new Task($i, function () use($i, $dataManager) {

                $time = 2;
                sleep($time);

                $response = $time . " seconds";

                $dataManager->modifyData(function($data) use($i, $response) {
                    $data[$i] = $response;
                    return $data;
                });

                return $response;

            }));
        }

        // Initialize the task executor
        $executor = new TaskExecutor($dag);

        // Execute tasks
        $executor->execute();

        $executionTime = $executor->getExecutionTime();
        print "\nAll tasks executed in  $executionTime seconds\n";

        $allResults = $executor->getResults();
        $lastResult = $executor->getFinalResult();

        //print "\nAll Results\n";
        //print_r($allResults);

        print "\nFinal Result\n";
        print_r($lastResult->getException());

        print "\nShared Data\n";
        print_r($dataManager->getData());

        print "\nAll tasks completed in {$executor->getExecutionTime()} seconds \n";

        // Assert that all tasks completed within the expected time frame
        // Since tasks A and B can run in parallel, and C waits for A, the total time should be close to 3 seconds
        $this->assertLessThanOrEqual(2, $executionTime, "Tasks took longer than expected to execute.");
    }
}
