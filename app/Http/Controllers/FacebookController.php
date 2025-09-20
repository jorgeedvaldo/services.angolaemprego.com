<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class FacebookController extends Controller
{
    /**
     * Publish a post on Facebook page feed.
     *
     * @param string $message
     * @param string|null $link
     * @return array
     */
    public function publishPost(string $message, string $link = null)
    {
        try {
            // Values from config
            $accessToken = config('services.facebookapi.token');
            $apiUrl      = "https://graph.facebook.com/v18.0/me/feed";

            $client = new Client();

            $params = [
                'form_params' => [
                    'message'      => $message,
                    'access_token' => $accessToken,
                ],
            ];

            if ($link) {
                $params['form_params']['link'] = $link;
            }

            $response = $client->post($apiUrl, $params);

            return json_decode($response->getBody()->getContents(), true);

        } catch (\Exception $e) {
            return [
                'error'   => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Publish a photo with caption on Facebook page.
     *
     * @param string $imageUrl
     * @param string|null $caption
     * @return array
     */
    public function publishPhoto(string $imageUrl, string $caption = null)
    {
        try {
            // Values from config
            $accessToken = config('services.facebookapi.token');
            $apiUrl      = "https://graph.facebook.com/v18.0/me/photos";

            $client = new Client();

            $params = [
                'form_params' => [
                    'url'          => $imageUrl,
                    'access_token' => $accessToken,
                ],
            ];

            if ($caption) {
                $params['form_params']['caption'] = $caption;
            }

            $response = $client->post($apiUrl, $params);

            return json_decode($response->getBody()->getContents(), true);

        } catch (\Exception $e) {
            return [
                'error'   => true,
                'message' => $e->getMessage(),
            ];
        }
    }
}
