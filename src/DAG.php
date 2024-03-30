<?php

namespace JumaPhelix\DAG;

class DAG {

    private $tasks = [];
    public $parentToChildren = [];

    public function addTask(Task $task) {
        $this->tasks[$task->getId()] = $task;
    }

    public function addParent($childId, $parentId) {
        if (!isset($this->parentToChildren[$parentId])) {
            $this->parentToChildren[$parentId] = [];
        }
        $this->parentToChildren[$parentId][] = $childId;
    }

    // Get children of a task
    public function getChildren($parentId) {
        return $this->parentToChildren[$parentId] ?? [];
    }

    public function getTask($taskId): ?Task {
        return $this->tasks[$taskId] ?? null;
    }

    public function topologicalSort() {

        // Kahn's algorithm for Topological Sorting
        $inDegree = array_fill_keys(array_keys($this->tasks), 0);

        // Calculate in-degree for all tasks
        foreach ($this->parentToChildren as $parentId => $children) {
            foreach ($children as $child) {
                $inDegree[$child]++;
            }
        }

        // Initialize queue with all tasks having no parents
        $queue = new \SplQueue();
        foreach ($inDegree as $task => $deg) {
            if ($deg == 0) {
                $queue->enqueue($task);
            }
        }

        $sortedOrder = [];
        while (!$queue->isEmpty()) {
            $current = $queue->dequeue();
            $sortedOrder[] = $current;

            if (!empty($this->parentToChildren[$current])) {
                foreach ($this->parentToChildren[$current] as $child) {
                    $inDegree[$child]--;
                    if ($inDegree[$child] == 0) {
                        $queue->enqueue($child);
                    }
                }
            }
        }

        if (count($sortedOrder) != count($this->tasks)) {
            throw new \RuntimeException("Detected a cycle in the DAG");
        }

        return $sortedOrder;
    }

}
