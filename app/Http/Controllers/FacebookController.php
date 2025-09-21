<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SocialMedia\FacebookService;

class FacebookController extends Controller
{
    protected FacebookService $facebookService;

    public function __construct(protected FacebookService $facebookService)
    {
        $this->facebookService = $facebookService;
    }
    public function post(string $message, string $link)
    {
        $this->facebookService->post($message, $link);
    }
}
