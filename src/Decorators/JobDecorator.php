<?php

namespace Lorisleiva\Actions\Decorators;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lorisleiva\Actions\Concerns\DecorateActions;

class JobDecorator implements ShouldQueue
{
    use DecorateActions;
    use InteractsWithQueue;
    use Queueable;
    use Batchable;
    use SerializesModels {
        __sleep as protected sleepFromSerializesModels;
        __wakeup as protected wakeupFromSerializesModels;
        __serialize as protected serializeFromSerializesModels;
        __unserialize as protected unserializeFromSerializesModels;
    }

    public int $tries;
    public int $maxExceptions;
    public int $timeout;

    protected string $actionClass;
    protected array $parameters = [];

    public function __construct(string $action, ...$parameters)
    {
        $this->actionClass = $action;
        $this->setAction(app($action));
        $this->parameters = $parameters;
        $this->constructed();
    }

    protected function constructed()
    {
        if ($this->hasProperty('jobConnection')) {
            $this->onConnection($this->getProperty('jobConnection'));
        }

        if ($this->hasProperty('jobQueue')) {
            $this->onQueue($this->getProperty('jobQueue'));
        }

        if ($this->hasProperty('jobTries')) {
            $this->setTries($this->getProperty('jobTries'));
        }

        if ($this->hasProperty('jobMaxExceptions')) {
            $this->setMaxExceptions($this->getProperty('jobMaxExceptions'));
        }

        if ($this->hasProperty('jobTimeout')) {
            $this->setTimeout($this->getProperty('jobTimeout'));
        }

        if ($this->hasMethod('configureJob')) {
            $this->callMethod('configureJob', [$this]);
        }
    }

    public function handle()
    {
        if ($this->hasMethod('asJob')) {
            return $this->callMethod('asJob', $this->parameters);
        }

        if ($this->hasMethod('handle')) {
            return $this->callMethod('handle', $this->parameters);
        }
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param int $tries
     * @return $this
     */
    public function setTries(int $tries)
    {
        $this->tries = $tries;

        return $this;
    }

    /**
     * @param int $maxException
     * @return $this
     */
    public function setMaxExceptions(int $maxException)
    {
        $this->maxExceptions = $maxException;

        return $this;
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function decorates(string $actionClass): bool
    {
        return $this->getAction() instanceof $actionClass;
    }

    public function backoff()
    {
        return $this->fromActionMethodOrProperty(
            'getJobBackoff',
            'jobBackoff',
            null,
            $this->parameters
        );
    }

    public function retryUntil()
    {
        return $this->fromActionMethodOrProperty(
            'getJobRetryUntil',
            'jobRetryUntil',
            null,
            $this->parameters
        );
    }

    public function middleware()
    {
        return $this->hasMethod('getJobMiddleware')
            ? $this->callMethod('getJobMiddleware', $this->parameters)
            : [];
    }

    public function displayName(): string
    {
        return $this->hasMethod('getJobDisplayName')
            ? $this->callMethod('getJobDisplayName', $this->parameters)
            : get_class($this->action);
    }

    public function tags()
    {
        return $this->hasMethod('getJobTags')
            ? $this->callMethod('getJobTags', $this->parameters)
            : [];
    }

    protected function serializeProperties()
    {
        $this->action = $this->actionClass;

        array_walk($this->parameters, function (&$value) {
            $value = $this->getSerializedPropertyValue($value);
        });
    }

    protected function unserializeProperties()
    {
        $this->setAction(app($this->actionClass));

        array_walk($this->parameters, function (&$value) {
            $value = $this->getRestoredPropertyValue($value);
        });
    }

    public function __sleep()
    {
        $this->serializeProperties();

        return $this->sleepFromSerializesModels();
    }

    public function __wakeup()
    {
        $this->wakeupFromSerializesModels();
        $this->unserializeProperties();
    }

    public function __serialize()
    {
        $this->serializeProperties();

        return $this->serializeFromSerializesModels();
    }

    public function __unserialize(array $values)
    {
        $this->unserializeFromSerializesModels($values);
        $this->unserializeProperties();
    }
}
