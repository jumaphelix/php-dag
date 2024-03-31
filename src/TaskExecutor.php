<?php

namespace JumaPhelix\DAG;

class TaskExecutor {

    private $dag;

    /**
     * @var Task[]
     */
    private array|null $results = [];
    private $taskResultsTable;
    private $startTime;
    private $endTime;
    private $taskResults = [];

    public function __construct(DAG $dag) {
        $this->dag = $dag;
        $this->initializeResultsTable();
    }

    /**
     * @return void
     */
    private function initializeResultsTable() {

        // We create a table with 2 columns: one for holding task result and the other for holding data shared by all tasks
        $this->taskResultsTable = new \Swoole\Table(1024);
        $this->taskResultsTable->column('result', \Swoole\Table::TYPE_STRING, 20480);
        $this->taskResultsTable->create();

    }

    public function execute() {

        $this->startTime = microtime(true);

        \Swoole\Coroutine\run(function () {

            $sortedTasks = $this->dag->topologicalSort();

            foreach ($sortedTasks as $taskId) {

                $task = $this->dag->getTask($taskId);

                // Set as pending
                $task->setStatus(TaskStatus::PENDING);

                // This has been corrected to reflect that we're dealing with tasks
                // that may depend on the results of their parent tasks
                $parents = array_keys(array_filter($this->dag->parentToChildren, function($children) use ($taskId) {
                    return in_array($taskId, $children);
                }));

                \Swoole\Coroutine::create(function () use ($task, $parents, $taskId) {

                    $parentResults = [];
                    foreach ($parents as $parentId) {

                        // Wait for the parent task to complete and get its result
                        $parentResultSerialized = $this->taskResultsTable->get($parentId, 'result');
                        if ($parentResultSerialized !== false) {
                            $parentResults[$parentId] = unserialize($parentResultSerialized);
                        }
                    }

                    // Execute the task, potentially using results from parent tasks
                    $result = null;
                    try {

                        // Mark as started
                        $task->setStatus(TaskStatus::RUNNING);

                        // Execute the task
                        $result = call_user_func($task->getCallable(), $parentResults);

                        // Mark as completed (successfully)
                        $task->setStatus(TaskStatus::COMPLETED);

                    } catch (\Throwable|\Exception $e) {

                        // Failed. Set the exception
                        $task->setException($e->getMessage());
                        $task->setExceptionTrace($e->getTrace());

                        // Mark status as failed
                        $task->setStatus(TaskStatus::FAILED);

                    }
                    // Set the task result
                    $task->setResult($result);

                    // Store result in the Swoole Table
                    $this->taskResultsTable->set($taskId, ['result' => serialize($result)]);

                    $this->results[] = $task;

                });
            }
        });

        // We clean the Swoole table
        $this->cleanAndGetResults();

        $this->endTime = microtime(true);
    }

    /**
     * @return Task[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return Task|null
     */
    public function getFinalResult(): ?Task {

        $lastKey = key(array_slice($this->results, -1, 1, true));

        return $this->results[$lastKey] ?? null;
    }

    private function cleanAndGetResults() {

        // Iterate over the Swoole Table to collect results
        foreach ($this->taskResultsTable as $taskId => $row) {
            // Unserialize the result before adding it to the results array
            $result = unserialize($row['result']);
            $this->taskResults[$taskId] = $result;
        }

        // Clean the table if no longer needed
        unset($this->taskResultsTable);
    }

    public function getTaskResults() {
        return $this->taskResults;
    }

    /**
     * @return mixed
     */
    public function getExecutionTime() {
        return $this->endTime - $this->startTime;
    }

}
