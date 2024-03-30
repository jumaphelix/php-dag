# PHP Directed Acyclic Graphs (DAG) 

- Implementation of Directed Acyclic Graphs (DAG) based task executions with parallelization in PHP
- When you have a set of tasks you want to execute but some tasks have dependencies, meaning they should only run after their 
dependencies have completed, then this is the package for you.
- The tasks are first resolved based on their dependencies and are executed in parallel using [Swoole](https://github.com/swoole/swoole-src). 
Tasks can get output from their dependencies in case they rely on that data for their executions.
- You can also use this package directly as a way to parallelize execution of synchronous tasks.   


# REQUIREMENTS

* PHP >= 8.1
* [Swoole](https://github.com/swoole/swoole-src): This package only works after you have installed Swoole extension and 
enabled the extension in your php.ini configuration file. 

# INSTALLATION

```
composer require phelixjuma/php-dag
```

# USAGE

Consider a set of 5 tasks A,B,C,D and E such that task A can only run after task D and B after C:
C -> B
D -> A
E
For this case, tasks C, D and E can be executed concurrently while task A should only start as soon as D completes and task B
should also only start as soon as task C completes. 

### DAG Topological Sort
We can represent these tasks as a DAG as long as there are no cyclic dependencies and then sort the tasks in order of 
execution based on the given information

```php

use JumaPhelix\DAG\DAG;
use JumaPhelix\DAG\Task;

// instantiate DAG
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

// Define dependencies with the first param defining the child and the second defining the parent
$dag->addParent('A', 'D');
$dag->addParent('B', 'C');

// Sort the tasks to show the order of execution
$sortedTasks = $dag->topologicalSort();

```

### Task Execution with Dependencies

Let's see a sample example of the above case with simple task implementations

```php

function taskA($parentResults, $name) {

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

// Here, we are suuming that all the tasks will be modifying this same data - while running on their own but respecting the dependencies
$data = [];

// Set up the tasks as DAG
$dag = new DAG();

// We define a shared data manager that allows concurrent tasks to modify the same data by reference in a lock-safe manner avoids data corruption
$dataManager = new SharedDataManager($data);

// Create tasks as instances or mocks of Task
$taskA = new Task('A', function ($parentResults = null) use($dataManager) {

    $args = ['name' => "Phelix"];

    // This task calls an external file
    $result = taskA($parentResults, ...$args);

    // Modify the shared data in a lock-safe way
    $dataManager->modifyData(function($data) use($result) {
        $data['A'] = $result;
        return $data;
    });

    return $result;

});

// This task handles everything within a closure 
$taskB = new Task('B', function($parentResults = null) use($dataManager)  {

    sleep(2);

    $message = "";
    // if there's data from a parent task, it can be consumed as shown here
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
$dag->addParent('B', 'C');

// Initialize the task executor
$executor = new TaskExecutor($dag);

// Execute tasks
$executor->execute();

$executionTime = $executor->getExecutionTime(); // Tasks will run in parallel and execute in a much shorter time than if they were run synchronously

// We can get all results which from each of the tasks
$allResults = $executor->getResults();

// Or we can get the result from the last task
$lastResult = $executor->getFinalResult();

// We can get the final value of the shared data as modified by all the tasks
$sharedData = $dataManager->getData();

``` 

### Basic Parallelization

Assume you just want to parallelize tasks that have no dependencies. For instance, I need to loop 1 thousand records from 
a database and do an operation that takes 1 second in each, the total time will be 1000 seconds, if the loop runs normally 
in PHP. We can parallelize the execution such that the total time remains 1 second irrespective of the number of records.

```php

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

// Total execution time will be approx 2 seconds down from 20,000 seconds had the tasks run in a normal loop
$executionTime = $executor->getExecutionTime();

// All results from each of the tasks
$allResults = $executor->getResults();

// The result from the last task to execute
$lastResult = $executor->getFinalResult();

// Shared data final state
$sharedData = $dataManager->getData();

```
