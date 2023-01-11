Dynamic Model for Laravel
===========================

Install: composer require sdon2/laravel-dynamic-model

Important Note: It requires doctrine/dbal package to be installed.

Now you can access dynamic tables by extending DynamicModel using the constructor.

Example:

use Sdon2\Laravel\DynamicModel;

class SampleTable extends DynamicModel
{
    public function __construct($attributes = [])
    {
        parent::__construct('dynamic_table_1', $attributes);
    }
}

Refer: https://github.com/sdon2/laravel-dynamic-model



