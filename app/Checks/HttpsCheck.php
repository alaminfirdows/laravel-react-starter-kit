<?php

namespace App\Checks;

use App\Checks\Support\Target;
use Illuminate\Http\Client\Response;

class HttpsCheck extends HttpCheck
{
    public function label(): string
    {
        return __('HTTPS works');
    }

    protected function path(): string
    {
        return '/';
    }

    protected function evaluate(Response $response, Target $target): CheckResult
    {
        return CheckResult::pass(__(':origin answers over HTTPS (HTTP :status).', ['origin' => $target->origin(), 'status' => $response->status()]));
    }
}
