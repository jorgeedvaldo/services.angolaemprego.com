<?php

namespace App\Services;

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

    /**
     * Publica uma foto com a legenda (usado nas vagas do Brasil)
     */
    public function postImage(string $message, string $imageUrl)
    {
        return $this->http->post('https://graph.facebook.com/v18.0/me/photos', [
            'form_params' => [
                'url' => $imageUrl,
                'caption' => $message,
                'access_token' => $this->token,
            ]
        ]);
    }
}
