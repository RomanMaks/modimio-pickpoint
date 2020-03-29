<?php

namespace app\components\factory;

use insolita\muffin\Factory as insolitaFactory;

/**
 * Class Factory
 * @package app\components\factory
 */
class Factory extends insolitaFactory
{
    /**
     * The registered after creating callbacks.
     *
     * @var array
     */
    protected $afterCreating = [];

    /**
     * Define a callback to run after creating a model with given state.
     *
     * @param  string  $class
     * @param  string  $state
     * @param  callable  $callback
     * @return $this
     */
    public function afterCreatingState($class, $state, callable $callback)
    {
        return $this->afterCreating($class, $callback, $state);
    }

    /**
     * Define a callback to run after creating a model.
     *
     * @param  string  $class
     * @param  callable  $callback
     * @param  string $name
     * @return $this
     */
    public function afterCreating($class, callable $callback, $name = 'default')
    {
        $this->afterCreating[$class][$name][] = $callback;

        return $this;
    }

    /**
     * Create a builder for the given model.
     *
     * @param  string $class
     * @param  string $name
     *
     * @return FactoryBuilder
     */
    public function of($class, $name = 'default')
    {
        return new FactoryBuilder(
            $class, $name, $this->definitions, $this->states, $this->afterCreating, $this->faker
        );
    }
}