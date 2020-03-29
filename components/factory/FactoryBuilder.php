<?php

namespace app\components\factory;

use Faker\Generator as Faker;
use insolita\muffin\FactoryBuilder as insolitaFactoryBuilder;
use yii\db\ActiveRecord;

/**
 * Class FactoryBuilder
 * @package app\components\factory
 */
class FactoryBuilder extends insolitaFactoryBuilder
{
    /**
     * The model after creating callbacks.
     *
     * @var array
     */
    protected $afterCreating = [];

    /**
     * FactoryBuilder constructor.
     * @param $class
     * @param $name
     * @param array $definitions
     * @param array $states
     * @param array $afterCreating
     * @param Faker $faker
     */
    public function __construct($class, $name, array $definitions, array $states, array $afterCreating, Faker $faker)
    {
        $this->afterCreating = $afterCreating;

        parent::__construct($class, $name, $definitions, $states, $faker);
    }

    /**
     * Set the state to be applied to the model.
     *
     * @param  string  $state
     * @return $this
     */
    public function state($state)
    {
        return $this->states([$state]);
    }

    /**
     * Create a collection of models and persist them to the database.
     *
     * @param  array $attributes
     *
     * @return  mixed
     * @throws \InvalidArgumentException
     */
    public function create(array $attributes = [])
    {
        $results = $this->make($attributes);

        if ($results instanceof ActiveRecord) {
            $this->store([$results]);

            $this->callAfterCreating([$results]);
        } else {
            $this->store($results);

            $this->callAfterCreating($results);
        }

        return $results;
    }

    /**
     * Run after creating callbacks on a collection of models.
     *
     * @param  array|ActiveRecord[] $models
     * @return void
     */
    public function callAfterCreating($models)
    {
        $this->callAfter($this->afterCreating, $models);
    }

    /**
     * Call after callbacks for each model and state.
     *
     * @param  array  $afterCallbacks
     * @param  array|ActiveRecord[] $models
     * @return void
     */
    protected function callAfter(array $afterCallbacks, $models)
    {
        $states = array_merge([$this->name], $this->activeStates);

        foreach ($models as $model) {
            foreach ($states as $state) {
                $this->callAfterCallbacks($afterCallbacks, $model, $state);
            }
        }
    }

    /**
     * Call after callbacks for each model and state.
     *
     * @param  array  $afterCallbacks
     * @param  ActiveRecord $model
     * @param  string  $state
     * @return void
     */
    protected function callAfterCallbacks(array $afterCallbacks, $model, $state)
    {
        if (! isset($afterCallbacks[$this->class][$state])) {
            return;
        }

        foreach ($afterCallbacks[$this->class][$state] as $callback) {
            $callback($model, $this->faker);
        }
    }
}