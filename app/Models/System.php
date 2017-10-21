<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class System extends Eloquent
{

	protected $connection = 'mongo';

}
