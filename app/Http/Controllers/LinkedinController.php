<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;

class LinkedInController extends Controller
{
    protected $client;
    protected $accessToken;
    protected $pageId;

    public function __construct()
    {
        $this->client      = new Client();
        $this->accessToken = config('services.linkedinapi.token');
        $this->pageId      = config('services.linkedinapi.page_id', '99975145');
    }

    /**
     * Publish a simple text post
     */
    public function publishText(string $message)
    {
        return $this->publish($message);
    }

    /**
     * Publish a post with a link
     */
    public function publishLink(string $message, string $link)
    {
        return $this->publish($message, $link);
    }

    /**
     * Publish a post with an image
     */
    public function publishImage(string $message, string $imagePath)
    {
        $assetUrn = $this->uploadImageToLinkedIn($imagePath);

        if (!$assetUrn) {
            return ['error' => 'Image upload failed'];
        }

        return $this->publish($message, null, $assetUrn);
    }

    /**
     * Generic method to handle publishing
     */
    protected function publish(string $message, string $link = null, string $assetUrn = null)
    {
        try {
            $media = [];

            if ($assetUrn) {
                $media[] = [
                    "status" => "READY",
                    "media"  => $assetUrn,
                ];
            } elseif ($link) {
                $media[] = [
                    "status"      => "READY",
                    "originalUrl" => $link,
                ];
            }

            $postContent = [
                "author" => "urn:li:organization:" . $this->pageId,
                "lifecycleState" => "PUBLISHED",
                "specificContent" => [
                    "com.linkedin.ugc.ShareContent" => [
                        "shareCommentary" => [
                            "text" => $message,
                        ],
                        "shareMediaCategory" => $assetUrn ? "IMAGE" : ($link ? "ARTICLE" : "NONE"),
                        "media" => $media,
                    ],
                ],
                "visibility" => [
                    "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC",
                ],
            ];

            $response = $this->client->post('https://api.linkedin.com/v2/ugcPosts', [
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type'  => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ],
                'json' => $postContent,
            ]);

            if ($response->getStatusCode() == 201) {
                return ['success' => true, 'message' => 'Post published successfully'];
            }

            return ['error' => 'Unexpected response from LinkedIn'];

        } catch (\Exception $e) {
            return ['error' => 'Failed to publish: ' . $e->getMessage()];
        }
    }

    /**
     * Upload an image to LinkedIn and return the asset URN
     */
    protected function uploadImageToLinkedIn(string $imagePath)
    {
        try {
            // 1. Register upload
            $registerResponse = $this->client->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type'  => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ],
                'json' => [
                    "registerUploadRequest" => [
                        "owner" => "urn:li:organization:" . $this->pageId,
                        "recipes" => ["urn:li:digitalmediaRecipe:feedshare-image"],
                        "serviceRelationships" => [[
                            "identifier" => "urn:li:userGeneratedContent",
                            "relationshipType" => "OWNER",
                        ]],
                    ],
                ],
            ]);

            $registerData = json_decode($registerResponse->getBody()->getContents(), true);
            $uploadUrl = $registerData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $assetUrn  = $registerData['value']['asset'];

            // 2. Upload binary file
            $this->client->put($uploadUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type'  => mime_content_type($imagePath),
                ],
                'body' => fopen($imagePath, 'r'),
            ]);

            return $assetUrn;

        } catch (\Exception $e) {
            return null;
        }
    }
}
