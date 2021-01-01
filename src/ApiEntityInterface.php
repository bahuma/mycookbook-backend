<?php

namespace App;

/**
 * Has to be implemented by all entities that are exposed via api.
 *
 * @package App
 */
interface ApiEntityInterface {

    /**
     * Get the values of the entity that should be visible in api responses.
     *
     * @return array
     *   An array that can be JSON serialized.
     */
    public function getApiFields(): array;
}
