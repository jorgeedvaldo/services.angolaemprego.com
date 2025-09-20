<?php

namespace App\Services\SocialMedia;

use GuzzleHttp\Client;

class FacebookService
{
    protected $http;
    protected $token;

    public function __construct()
    {
        $this->http = new Client();
        $this->token = config('services.facebookapi.token');
    }

    public function post(string $message, string $link)
    {
        return $this->http->post('https://graph.facebook.com/v18.0/me/feed', [
            'form_params' => [
                'message' => $message,
                'link' => $link,
                'access_token' => $this->token,
            ]
        ]);
    }
}
