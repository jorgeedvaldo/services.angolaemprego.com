<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Services\SocialMediaService;
use App\Models\Link;

class JobService
{
    protected $http;
    protected $socialMedia;
    protected $geminiService;

    public function __construct()
    {
        $this->http = new Client(['verify' => false]); // SSL off por enquanto
        $this->geminiService = new GeminiService();
    }

    public function fetchFromWebsite(string $website = 'angoemprego.com')
    {
        $response = $this->http->get("https://{$website}/wp-json/wp/v2/job-listings");
        if ($response->getStatusCode() !== 200) {
            return;
        }

        $jobs = json_decode($response->getBody()->getContents());
        if (empty($jobs)) {
            return;
        }

        foreach ($jobs as $job) {
            // Evitar duplicados
            if (Link::where('url', $job->link)->exists()) {
                continue;
            }

            // Empresa
            $company = $job->meta->_company_name ?: 'Empresa em Angola';

            // Descrição simplificada
            $description = strip_tags($job->content->rendered);

            // Formatar descrição com Gemini
            $description = $this->geminiService->formatarDescricaoVaga($description);

            //Formatar Titulo com Gemini
            $job->title->rendered = $this->geminiService->gerarTituloVaga($job->title->rendered);

            // Salvar job no teu portal
            $savedJob = $this->saveJob([
                'title' => $job->title->rendered,
                'company' => $company,
                'location' => $job->meta->_job_location,
                'description' => $description,
                'email_or_link' => $job->meta->_application,
                'image' => 'images/jobs/default.png',
            ]);

            if (!$savedJob) {
                continue;
            }

            // Guardar referência na tabela Links
            Link::create([
                'url' => $job->link,
                'country_id' => 1,
            ]);
        }
    }

    /**
     * Salva o job no portal AngolaEmprego via API
     */
    protected function saveJob(array $jobData): ?array
    {
        try {
            $response = $this->http->post('https://angolaemprego.com/api/job/create', [
                'json' => $jobData,
            ]);

            if ($response->getStatusCode() === 201 || $response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }

            return null;
        } catch (\Exception $e) {
            \Log::error("Erro ao salvar job: " . $e->getMessage());
            return null;
        }
    }
}
