<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;

class GeminiController extends Controller
{
    protected $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    public function descricaoVaga(string $descricao)
    {
        return response()->json([
            'description' => $this->geminiService->formatarDescricaoVaga($descricao)
        ]);
    }

    public function tituloVaga(string $titulo)
    {
        return response()->json([
            'title' => $this->geminiService->gerarTituloVaga($titulo)
        ]);
    }

    public function getEmailOuLink(string $conteudo)
    {
        return response()->json([
            'email_or_link' => $this->geminiService->extrairEmailOuLink($conteudo)
        ]);
    }
}
