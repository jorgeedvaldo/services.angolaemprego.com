<?php

namespace App\Services;

use GuzzleHttp\Client;

class LinkedInService
{
    protected $http;
    protected $token;
    protected $pageId;

    public function __construct()
    {
        $this->http = new Client();
        $this->token = config('services.linkedinapi.token');   // token vem do .env
        $this->pageId = config('services.linkedinapi.page_id'); // adiciona no config/services.php
    }

    public function post(string $message, string $link, ?string $imageUrl = null)
    {
        $body = [
            'author' => "urn:li:organization:{$this->pageId}",
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $message
                    ],
                    'shareMediaCategory' => $imageUrl ? 'IMAGE' : 'ARTICLE',
                    'media' => [
                        [
                            'status' => 'READY',
                            'originalUrl' => $link,
                            'title' => ['text' => $message],
                        ]
                    ]
                ]
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
            ]
        ];

        return $this->http->post('https://api.linkedin.com/v2/ugcPosts', [
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'X-Restli-Protocol-Version' => '2.0.0',
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]);
    }
}
