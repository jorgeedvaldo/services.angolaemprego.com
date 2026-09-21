<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\LinkedinController;
use App\Models\Job;
use App\Models\SocialMediaJob;

class SocialPostController extends Controller
{
    /**
     * Identificação das vagas do Brasil na tabela countries do portal:
     * o código ISO manda, e o id 2 (a ordem em que os países foram inseridos)
     * serve de recurso quando a tabela não estiver acessível.
     */
    protected const BRAZIL_COUNTRY_CODE = 'BR';
    protected const BRAZIL_COUNTRY_ID = 2;

    /**
     * O LinkedIn rejeita comentários com mais de 3000 caracteres.
     */
    protected const LINKEDIN_MAX_LENGTH = 2900;

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
        $link    = $this->portalUrl() . "/vagas/" . $job->slug;
        $message = $job->title . "\n.\nMais detalhes aqui: " . $link . "\n.";

        // Post to Facebook
        $this->facebookController->post($message, $link);

        // Post to LinkedIn
        if ($this->isBrazilJob($job)) {
            // Vagas do Brasil: descrição completa com a imagem da vaga
            $this->publishBrazilJobToLinkedIn($job, $link);
        } elseif ($link) {
            $this->linkedInController->publishLink($message, $link);
        } else {
            $this->linkedInController->publishText($message);
        }

        return response()->json(['status' => 'Posts submitted']);
    }

    /**
     * Publica no LinkedIn uma vaga do Brasil: descrição completa acompanhada
     * da imagem da vaga. Sem imagem, publica na mesma com o link.
     */
    protected function publishBrazilJobToLinkedIn(Job $job, string $link): void
    {
        $message   = $this->buildFullMessage($job, $link, self::LINKEDIN_MAX_LENGTH);
        $imageUrl  = $this->jobImageUrl($job);
        $imagePath = $imageUrl ? $this->downloadImage($imageUrl) : null;

        try {
            if ($imagePath) {
                $this->linkedInController->publishImage($message, $imagePath);
            } else {
                $this->linkedInController->publishLink($message, $link);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao publicar a vaga do Brasil no LinkedIn: ' . $e->getMessage());
        } finally {
            if ($imagePath && file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
    }

    /**
     * Verifica se a vaga pertence ao Brasil.
     */
    protected function isBrazilJob(Job $job): bool
    {
        $code = $this->jobCountryCode($job);

        if ($code !== null) {
            return $code === self::BRAZIL_COUNTRY_CODE;
        }

        // Sem acesso à tabela countries fica o id com que o Brasil foi inserido.
        return (int) $job->country_id === self::BRAZIL_COUNTRY_ID;
    }

    /**
     * Código ISO do país da vaga, ou null quando não se consegue lê-lo.
     */
    protected function jobCountryCode(Job $job): ?string
    {
        try {
            $code = strtoupper(trim((string) optional($job->country)->code));

            return $code === '' ? null : $code;
        } catch (\Exception $e) {
            Log::error('Erro ao ler o país da vaga: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Monta a mensagem com o título, a descrição completa e o link da vaga.
     * Apenas a descrição é encurtada, para o link se manter na publicação.
     */
    protected function buildFullMessage(Job $job, string $link, int $maxLength): string
    {
        $header      = $job->title . "\n.\n";
        $footer      = "\n.\nMais detalhes aqui: " . $link . "\n.";
        $description = trim($this->LimparDescricao($job->description));

        $available = max($maxLength - mb_strlen($header) - mb_strlen($footer), 0);

        if (mb_strlen($description) > $available) {
            $description = rtrim(mb_substr($description, 0, max($available - 3, 0))) . '...';
        }

        return $header . $description . $footer;
    }

    /**
     * URL absoluta da imagem da vaga, ou null quando a vaga não tem imagem.
     */
    protected function jobImageUrl(Job $job): ?string
    {
        $image = trim((string) $job->image);

        if ($image === '') {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return rtrim($this->portalStorageUrl(), '/') . '/' . ltrim($image, '/');
    }

    /**
     * Descarrega a imagem para um ficheiro temporário — o upload do LinkedIn
     * precisa de um ficheiro local. Devolve null se o download falhar.
     */
    protected function downloadImage(string $imageUrl): ?string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'job_image_');

        if ($tempPath === false) {
            return null;
        }

        try {
            $client = new Client();
            $client->get($imageUrl, ['verify' => false, 'sink' => $tempPath]);

            clearstatcache(true, $tempPath);

            if (filesize($tempPath) > 0) {
                return $tempPath;
            }
        } catch (\Exception $e) {
            Log::error('Erro ao descarregar a imagem da vaga: ' . $e->getMessage());
        }

        @unlink($tempPath);

        return null;
    }

    protected function portalUrl(): string
    {
        return rtrim(config('services.portal.url', 'https://www.angolaemprego.com'), '/');
    }

    protected function portalStorageUrl(): string
    {
        return rtrim(config('services.portal.storage_url', 'https://angolaemprego.com/storage'), '/');
    }

    public function postLastToMedia()
    {
        $socialMediaJob = SocialMediaJob::where('post_status', false)
            ->first();
            
        if (!$socialMediaJob) {
            return response()->json(['status' => 'No pending posts']);
        }

        $job = Job::find($socialMediaJob->job_id);

        $this->postToSocialMedia($job);

        $socialMediaJob->post_status = true;
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
