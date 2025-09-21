<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\LinkedinController;
use App\Models\Job;
use App\Models\SocialMediaJob;

class SocialPostController extends Controller
{
    protected FacebookController $facebookController;
    protected LinkedinController $linkedInController;

    public function __construct(
        FacebookController $facebookController,
        LinkedinController $linkedInController
    ) {
        $this->facebookController = $facebookController;
        $this->linkedInController = $linkedInController;
    }

    public function postToSocialMedia(Job $job)
    {
        $link    = "https://www.angolaemprego.com/vagas/" . $job->slug;
        $message = $job->title . "\n.\nMais detalhes aqui: " . $link . "\n.";

        // Post to Facebook
        $this->facebookController->post($message, $link);

        // Post to LinkedIn
        if ($link) {
            $this->linkedInController->publishLink($message, $link);
        } else {
            $this->linkedInController->publishText($message);
        }

        return response()->json(['status' => 'Posts submitted']);
    }

    public function postLastToMedia()
    {
        $socialMediaJob = SocialMediaJob::where('posted', false)
            ->first();
        $job = Job::find($socialMediaJob->job_id);

        $this->postToSocialMedia($job);

        $socialMediaJob->posted = true;
        $socialMediaJob->save();

        return response()->json(['status' => 'Posts submitted']);
    }

    public function LimparDescricao($Text)
    {
		$NovaDescricao = str_replace("<br>", "\n<br>", $Text);
		$NovaDescricao = str_replace(["</p>", "</h1>", "</h2>", "</h3>", "</li>"], ["</p>\n", "</h1>\n", "</h2>\n", "</h3>\n", "</li>\n"], $NovaDescricao);
		$NovaDescricao = explode('----------', $NovaDescricao)[0];
		$NovaDescricao = strip_tags($NovaDescricao);
		$NovaDescricao = str_replace("&nbsp;", "", $NovaDescricao);

		return $NovaDescricao;
    }
}
