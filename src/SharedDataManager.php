<?php

namespace JumaPhelix\DAG;

use OpenSwoole;

class SharedDataManager {

    private $lock;
    private $data;

    public function __construct(&$data) {
        $this->lock = new \OpenSwoole\Lock(\OpenSwoole\Constant::MUTEX);
        $this->data = &$data;
    }

    public function modifyData(callable $modifier) {

        $this->lock->lock();
        try {

            // Call the modifier function, passing shared data by reference
            $this->data = call_user_func($modifier, $this->data);

        } finally {
            // Ensure the lock is released even if an exception is thrown
            $this->lock->unlock();
        }

    }

    /**
     * Get the data by reference
     *
     * @return mixed
     */
    public function &getData() {
        return $this->data;
    }
}
