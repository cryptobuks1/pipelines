<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines\File\Pipeline;

/**
 * Class StepsIterator
 *
 * @package Ktomk\Pipelines\File\Pipeline
 */
class StepsIterator implements \Iterator
{
    /**
     * @var \Iterator
     */
    private $inner;

    /**
     * @var int
     */
    private $index;

    /**
     * @var Step
     */
    private $current;

    /**
     * @var bool override trigger: manual in iteration
     */
    private $noManual = false;

    /**
     * StepsIterator constructor.
     *
     * @param \Iterator $iterator
     */
    public function __construct(\Iterator $iterator)
    {
        $this->inner = $iterator;
    }

    /**
     * @return null|int
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * Index of the pipeline step being iterated
     *
     * Undefined behaviour if the iteration has not yet been
     * started (e.g. the iterator has not yet been rewound)
     *
     * @return int
     */
    public function getStepIndex()
    {
        return $this->current->getIndex();
    }

    /**
     * Iteration might stop at a manual step. If
     * that is the case, isManual() will be true
     * *after* the iteration.
     *
     * @return bool
     */
    public function isManual()
    {
        return 0 !== $this->index
            && !$this->noManual
            && $this->current()
            && $this->current->isManual();
    }

    /** @see \Iterator * */

    public function next()
    {
        $this->index++;
        $this->inner->next();
    }

    public function key()
    {
        return $this->inner->key();
    }

    /**
     * @return Step
     */
    public function current()
    {
        return $this->current = $this->inner->current();
    }

    public function valid()
    {
        if ($this->isManual()) {
            return false;
        }

        return $this->inner->valid();
    }

    public function rewind()
    {
        $this->index = 0;
        $this->inner->rewind();
    }

    /**
     * @param bool $noManual
     */
    public function setNoManual($noManual)
    {
        $this->noManual = (bool)$noManual;
    }
}
