<?php

namespace App\Models\Concerns;

trait TransactionalExternalEvents
{
    public function save(array $options = [])
    {
        if (! config('external_api.webhooks_enabled')) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(fn () => parent::save($options));
    }
}
