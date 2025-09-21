<?php

namespace App\Services;

class SocialMediaService
{
    protected $facebook;
    protected $linkedin;

    public function __construct(FacebookService $facebook, LinkedInService $linkedin)
    {
        $this->facebook = $facebook;
        $this->linkedin = $linkedin;
    }

    public function publishJob(array $jobData)
    {
        $title = $jobData['title'];
        $link = $jobData['link'];
        $description = $jobData['description'];

        $this->facebook->post($title . "\n\n" . $description, $link);
        $this->linkedin->post($title . "\n\n" . $description, $link);
    }
}
