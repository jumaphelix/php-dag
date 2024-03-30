<?php

namespace JumaPhelix\DAG;

use Swoole\Coroutine\Channel;
use Swoole\Coroutine;
use Swoole\Lock;

class TaskExecutor {

    private $dag;

    /**
     * @var Task[]
     */
    private array|null $results = [];
    private $channels = [];
    private $startTime;
    private $endTime;

    public function __construct(DAG $dag) {
        $this->dag = $dag;
    }

    public function execute() {

        $this->startTime = microtime(true);

        Coroutine\run(function () {

            $sortedTasks = $this->dag->topologicalSort();

            // Initialize a channel for each task to synchronize execution and manage results
            foreach ($sortedTasks as $taskId) {
                $this->channels[$taskId] = new Channel(1);
            }

            foreach ($sortedTasks as $taskId) {

                $task = $this->dag->getTask($taskId);

                // Set as pending
                $task->setStatus(TaskStatus::PENDING);

                // This has been corrected to reflect that we're dealing with tasks
                // that may depend on the results of their parent tasks
                $parents = array_keys(array_filter($this->dag->parentToChildren, function($children) use ($taskId) {
                    return in_array($taskId, $children);
                }));

                Coroutine::create(function () use ($task, $parents, $taskId) {

                    $parentResults = [];
                    foreach ($parents as $parentId) {
                        // Wait for the parent task to complete and get its result
                        $parentResults[$parentId] = $this->channels[$parentId]->pop();
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

                    // Make the result available for this task's children
                    $this->channels[$taskId]->push($result);

                    $this->results[] = $task;

                });
            }
        });

        // Close channels after all tasks have completed
        foreach ($this->channels as $channel) {
            $channel->close();
        }

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

    /**
     * @return mixed
     */
    public function getExecutionTime() {
        return $this->endTime - $this->startTime;
    }

}
