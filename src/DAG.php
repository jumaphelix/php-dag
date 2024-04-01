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
            // Detected a cycle. We find the cycle and display it

            $cycle = $this->findFirstCycle();

            throw new \RuntimeException("Detected a cycle in the DAG: ".$cycle);
        }

        return $sortedOrder;
    }

    /**
     * @return string
     */
    public function visualize() {

        $sortedTasks = $this->topologicalSort();

        $representation = "Graphical Representation of DAG Tasks and Dependencies\n";
        $representation .= "----------------------------------------\n";

        foreach ($sortedTasks as $taskId) {

            $task = $this->getTask($taskId);

            $children = $this->getChildren($taskId);

            if (empty($children)) {
                $representation .= "{$taskId} [No children]\n";
            } else {
                $representation .= "{$taskId} -> (" . implode(', ', $children) . ")\n";
            }
        }

        $representation .= "----------------------------------------\n";
        $representation .= "Topological Order of Execution: \n";
        $representation .= implode(' -> ', $sortedTasks) . "\n";

        return $representation;
    }

    /**
     * @return string
     */
    public function findFirstCycle() {

        $visited = [];
        $recStack = [];
        $parentInfo = []; // To track the path for backtracking

        foreach ($this->tasks as $task) {
            if (!isset($visited[$task->getId()]) && $this->dfsFindCycle($task->getId(), $visited, $recStack, $parentInfo)) {
                // Cycle found, backtrack to get the cycle path
                $cyclePath = $this->backtrackCyclePath($task->getId(), $parentInfo);
                return $this->formatCycleMessage($cyclePath)." Summary ".json_encode($parentInfo);
            }
        }

        return "No cycle detected in the DAG.";
    }

    /**
     * @param $taskId
     * @param $visited
     * @param $recStack
     * @param $parentInfo
     * @return bool
     */
    private function dfsFindCycle($taskId, &$visited, &$recStack, &$parentInfo) {

        $visited[$taskId] = true;
        $recStack[$taskId] = true;

        foreach ($this->getChildren($taskId) as $childId) {
            if (!isset($visited[$childId])) {
                $parentInfo[$childId] = $taskId; // Track the parent
                if ($this->dfsFindCycle($childId, $visited, $recStack, $parentInfo)) return true;
            } elseif (isset($recStack[$childId]) && $recStack[$childId]) {
                // Cycle detected
                $parentInfo[$childId] = $taskId; // Include the last link of the cycle
                return true;
            }
        }

        $recStack[$taskId] = false;
        return false;
    }

    /**
     * @param $startId
     * @param $parentInfo
     * @return array
     */
    private function backtrackCyclePath($startId, $parentInfo) {
        $path = [];
        $currentId = $startId;

        // Start backtracking from the starting node
        while (isset($parentInfo[$currentId])) {
            $path[] = $currentId;
            $currentId = $parentInfo[$currentId];

            // Stop if we have completed the cycle
            if ($currentId == $startId) {
                break;
            }
        }

        // Add the starting node to close the cycle
        $path[] = $startId;
        return $path;
    }

    /**
     * @param $cyclePath
     * @return string
     */
    private function formatCycleMessage($cyclePath) {
        $messageParts = [];
        foreach ($cyclePath as $index => $taskId) {
            if (isset($cyclePath[$index + 1])) {
                $messageParts[] = "Task {$taskId} depends on Task {$cyclePath[$index + 1]}";
            }
        }
        return implode(", but also ", $messageParts) . ".";
    }

}
