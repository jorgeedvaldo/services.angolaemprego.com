<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Services\SocialMediaService;
use App\Models\Link;
use App\Models\Post;

class PostService
{
    protected $http;
    protected $socialMedia;
    protected $geminiService;
    public function __construct()
    {
        $this->http = new Client(['verify' => false]); // SSL off por enquanto
        $this->geminiService = new GeminiService();
    }

    public function fetchFromWebsite()
    {
        try {
            $response = $this->http->get('https://rna.ao/rna.ao/wp-json/wp/v2/posts');
        } catch (\Exception $e) {
            \Log::error('Falha na requisição HTTP para clickpetroleoegas: ' . $e->getMessage());
            return;
        }

        if ($response->getStatusCode() !== 200) {
            \Log::error('Falha ao buscar posts de clickpetroleoegas.com.br. Status: ' . $response->getStatusCode());
            return;
        }

        $posts = json_decode($response->getBody()->getContents());
        if (empty($posts)) {
            return;
        }

        foreach ($posts as $post) {
            // Evitar duplicados usando a tabela de referência 'links'
            if (Link::where('url', $post->link)->exists()) {
                continue;
            }

            // --- Processamento e Mapeamento dos Dados ---

            // Descrição simplificada (limpando HTML)
            $description = strip_tags($post->content->rendered);

            // Formatar descrição com Gemini
            $description = $this->geminiService->formatarDescricao($description); // Você pode querer criar um método/prompt específico para blog posts

            // Formatar Titulo com Gemini
            $title = $this->geminiService->gerarTitulo($post->title->rendered); // Idem acima


            // Salvar post no teu portal
            $savedPost = $this->createPost([
                'title' => $title,
                'description' => $description,
                'image' => 'images/posts/default.png',
            ]);

            if (!$savedPost) {
                continue;
            }

            // Guardar referência na tabela Links
            Link::create([
                'url' => $post->link,
                'country_id' => 1,
            ]);
        }
    }

    public function createPost(array $data)
    {
        try {
            $response = $this->http->post('https://angolaemprego.com/api/post/create', [
                'json' => $data,
            ]);

            if ($response->getStatusCode() === 201 || $response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }

            return null;
        } catch (\Exception $e) {
            \Log::error("Erro ao salvar post: " . $e->getMessage());
            return null;
        }
    }
}
