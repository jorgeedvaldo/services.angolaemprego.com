<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class GeminiService
{
    protected $client;
    protected $apiKey;
    protected $url;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('services.geminiapi.token');
        $this->url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent';
    }

    private function requestGemini($prompt, $format)
    {
        $response = $this->client->post($this->url, [
            'verify' => false,
            'headers' => ['Content-Type' => 'application/json'],
            'query'   => ['key' => $this->apiKey],
            'json'    => [
                "contents" => [["parts" => [["text" => $prompt]]]],
                "generationConfig" => [
                    "temperature" => 0.9,
                    "topK" => 1,
                    "topP" => 1,
                    "maxOutputTokens" => 2048
                ],
                "safetySettings" => [
                    ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"],
                    ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"],
                    ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"],
                    ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"]
                ],
            ],
        ]);

        $responseBody = $response->getBody()->getContents();
        $jsonObject = json_decode($responseBody);

        $jsonstring = strtr($jsonObject->candidates[0]->content->parts[0]->text, [
            '```' => "", 'json' => ""
        ]);

        $parsed = json_decode($jsonstring);

        if (!isset($parsed->{$format})) {
            throw ValidationException::withMessages([$format . ' não existe']);
        }

        return $parsed->{$format};
    }

    public function formatarDescricaoVaga($descricao)
    {
        $prompt = "tenho o seguinte texto e pretendo transformar em linguagem de marcação (formatação html) com bolds e titulos, etc para gravar como artigo na base de dados do meu site de empregos, dê um tratamento, o texto não necessariamente deve ficar igual mas deves fazer como se estivesses a criar um novo artigo e coloque o modo de se candidatar no final, por favor faça isso e envie os dados no seguinte formato JSON: {description: DESCRICÃO_EM_HYPERTEXTO}: " . $descricao;
        return $this->requestGemini($prompt, 'description');
    }

    public function formatarDescricao($descricao)
    {
        $prompt = "tenho o seguinte texto e pretendo que tu ESCREVAS UM ARTIGO PARA O MEU SITE, USE PT-BR E SE BASEIE NO TEXTO PARA GERAR O MEU ARTIGO. Deves transformar em linguagem de marcação (formatação html) com bolds e titulos, etc para gravar como artigo na base de dados do meu site, dê um tratamento e não esqueças que deves gerar um novo artigo, por favor faça isso e envie os dados no seguinte formato JSON: {description: DESCRICÃO_EM_HYPERTEXTO}. O texto para se inspirar é o seguinte: " . $descricao;
        return $this->requestGemini($prompt, 'description');
    }

    public function gerarTituloVaga($tituloAntigo = 'Administrador')
    {
        $prompt = 'Crie um titulo para esta vaga de emprego: "' . $tituloAntigo . '". Os titulos nunca devem ser na primeira pessoa e devem ter o estilo parecido com: Vaga para xxx, precisa-se de, grande oportunidade para, opotunidade urgente, etc. Quero o dado no seguinte formato json: {title: TITULO_DA_VAGA}';
        return $this->requestGemini($prompt, 'title');
    }

    public function gerarTitulo($tituloAntigo)
    {
        $prompt = 'Crie uma variação de titulo para este artigo: "' . $tituloAntigo . '". o titulo deve ser fácil de compreender e deve ter um bom SEO. Quero o dado no seguinte formato json: {title: TITULO_DO_ARTIGO}';
        return $this->requestGemini($prompt, 'title');
    }

    public function extrairEmailOuLink($conteudo)
    {
        $prompt = 'olhe a descrição do seguinte emprego: ' . $conteudo . ' leia os dados e retire o endereço para aplicar à vaga (email ou link) e me dê os dados em json no seguinte formato  {email_or_link: EMAIL_OR _LINK}';
        return $this->requestGemini($prompt, 'email_or_link');
    }
}
