<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Routing\Controller;

abstract class BaseController extends Controller
{
    use ApiResponse;
}
