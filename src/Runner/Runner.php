<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines\Runner;

use Ktomk\Pipelines\Cli\Exec;
use Ktomk\Pipelines\Cli\Streams;
use Ktomk\Pipelines\File\Pipeline;
use Ktomk\Pipelines\File\Pipeline\Step;

/**
 * Pipeline runner with docker under the hood
 */
class Runner
{
    const STATUS_NO_STEPS = 1;
    const STATUS_RECURSION_DETECTED = 127;

    /**
     * @var RunOpts
     */
    private $runOpts;

    /**
     * @var Directories
     */
    private $directories;

    /**
     * @var Exec
     */
    private $exec;

    /**
     * @var Flags
     */
    private $flags;

    /**
     * @var Env
     */
    private $env;
    /**
     * @var Streams
     */
    private $streams;

    /**
     * Static factory method.
     *
     * The "ex" version of runner creation, moving creation out of the ctor itself.
     *
     * @param RunOpts $runOpts
     * @param Directories $directories source repository root directory based directories object
     * @param Exec $exec
     * @param Flags $flags [optional]
     * @param Env $env [optional]
     * @param Streams $streams [optional]
     *
     * @return Runner
     */
    public static function createEx(
        RunOpts $runOpts,
        Directories $directories,
        Exec $exec,
        Flags $flags = null,
        Env $env = null,
        Streams $streams = null
    )
    {
        $flags = null === $flags ? new Flags() : $flags;
        $env = null === $env ? Env::create() : $env;
        $streams = null === $streams ? Streams::create() : $streams;

        return new self($runOpts, $directories, $exec, $flags, $env, $streams);
    }

    /**
     * Runner constructor.
     *
     * @param string $runOpts
     * @param Directories $directories source repository root directory based directories object
     * @param Exec $exec
     * @param Flags $flags
     * @param Env $env
     * @param Streams $streams
     */
    public function __construct(
        RunOpts $runOpts,
        Directories $directories,
        Exec $exec,
        Flags $flags,
        Env $env,
        Streams $streams
    )
    {
        $this->runOpts = $runOpts;
        $this->directories = $directories;
        $this->exec = $exec;
        $this->flags = $flags;
        $this->env = $env;
        $this->streams = $streams;
    }

    /**
     * @param Pipeline $pipeline
     *
     * @throws \RuntimeException
     * @return int status (as in exit status, 0 OK, !0 NOK)
     */
    public function run(Pipeline $pipeline)
    {
        $hasId = $this->env->setPipelinesId($pipeline->getId()); # TODO give Env an addPipeline() method (compare addReference)
        if ($hasId) {
            $this->streams->err(sprintf(
                "pipelines: won't start pipeline '%s'; pipeline inside pipelines recursion detected\n",
                $pipeline->getId()
            ));

            return self::STATUS_RECURSION_DETECTED;
        }

        $steps = $pipeline->getSteps()->getIterator();
        $steps->setNoManual($this->runOpts->isNoManual());
        list($status, $steps) = $this->runSteps($steps);

        if (0 === $status && $steps->isManual()) {
            $this->streams->err(sprintf(
                "pipelines: step #%d is manual. use `--steps %d-` to continue or `--no-manual` to override\n",
                $steps->getStepIndex() + 1,
                $steps->getStepIndex() + 1
            ));
        }

        return $status;
    }

    /**
     * @param \Ktomk\Pipelines\File\Pipeline\Step $step
     *
     * @return int status (as in exit status, 0 OK, !0 NOK)
     */
    public function runStep(Step $step)
    {
        $stepRunner = new StepRunner(
            $this->runOpts,
            $this->directories,
            $this->exec,
            $this->flags,
            $this->env,
            $this->streams
        );

        return $stepRunner->runStep($step);
    }

    /**
     * @param Pipeline\StepsIterator $steps
     *
     * @return array(int, Pipeline\StepsIterator)
     */
    private function runSteps(Pipeline\StepsIterator $steps)
    {
        foreach ($steps as $step) {
            $status = $this->runStep($step);
            if (0 !== $status) {
                break;
            }
        }

        if (!isset($status)) {
            $this->streams->err("pipelines: pipeline with no step to execute\n");

            return array(self::STATUS_NO_STEPS, $steps);
        }

        return array($status, $steps);
    }
}
