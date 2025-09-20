<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Models\Link;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Exception;

class LinkController extends Controller
{
	public function ObterAngolaEmpregoAngoEmprego($website = 'angoemprego.com')
	{
		// Crie uma instância do cliente Guzzle
		$client = new Client();

		// Faça uma requisição GET para a URL desejada
		$response = $client->request('GET', 'https://' . $website . '/wp-json/wp/v2/job-listings', ['verify' => false]);

		$Empregos = [];

		// Verifique se a requisição foi bem-sucedida (código de status 200)
		if ($response->getStatusCode() === 200) {
			// Obtenha o conteúdo da resposta em formato JSON
			$json = $response->getBody()->getContents();
            
			// Decodifique o JSON para um array ou objeto PHP
			$data = json_decode($json);

			if (!empty($data)) {
				$Empregos = $data;
			}
		}
		
		foreach ($Empregos as $emprego) {

            // Verifica se o link já existe, se sim salte para a proxima iteração
            if(Link::where('url', $emprego->link)->exists())
            {
                continue;
            }

            //Ignorar vagas de AngoEmprego Pro
            if (strpos($emprego->meta->_application, "empregopro.ao") !== false) {
				continue;
			}

            //Ignorar vagas de Jobartis
            if (strpos($emprego->meta->_application, "jobartis.com") !== false) {
				//continue;
			}

            //Ignorar vagas de Linkedin
            if (strpos($emprego->meta->_application, "linkedin.com") !== false) {
				//continue;
			}

            //Ignorar vagas de Links
            if (strpos($emprego->meta->_application, "@") == false) {
                //continue;
            }

            // Trate a descrição
            $ExplodeText = explode('Se você tem interesse nesta oportunidade de emprego', $emprego->content->rendered)[0];
            $ExplodeText = explode('Como se Candidatar:', $ExplodeText)[0];
            $ExplodeText = explode('<a href=\"https://angoemprego.com/', $ExplodeText)[0];
			$descricao = $this->DescricaoVagaViaGemini($ExplodeText);
            $descricaoTratada = $descricao;
			$MinhaMarca = '<h2>-------------</h2><h2>Empregos Yoyota - Aqui você encontra o seu emprego ideal.</h2><p>Encontre aqui as melhores vagas de emprego para 2024, oportunidades de recrutamento em Angola disponíveis no nosso portal para candidaturas. Também informamos sobre concurso público para 2024 e muito mais.<br /><strong>Tags:</strong>&nbsp;emprego em Angola, Emprego em Angola 2024, Emprego em Luanda, Recrutamento 2024, Recrutamento em Angola</p><h2>Não recrutamos ninguém, a nossa missão é informar as vagas de emprego publicadas no Jornal de Angola e de outras fontes credíveis.</h2>';

			// Crie um texto de candidatura
			$TextoCandidatura = "";

			if (strpos($emprego->meta->_application, "@") !== false) {
				$TextoCandidatura = '<h1>Passos para se inscrever:</h1><p>Faça a sua candidatura através do e-mail: <a href="mailto:' . $emprego->meta->_application . '">' . $emprego->meta->_application .  '</a></p>';
			} elseif ($emprego->meta->_application !== "") {
				$TextoCandidatura = '<h1>Passos para se inscrever:</h1><p>Faça a sua candidatura através do link: <a href="' . $emprego->meta->_application . '">' . $emprego->meta->_application .  '</a></p>';
			}

            //Criar Titulo com IA
            $IATitle = $emprego->title->rendered;
            $IATitle = $this->TituloVagaViaGemini($emprego->title->rendered);
            $IAAplication = $emprego->meta->_application;
            $Empresa = $emprego->meta->_company_name;
            
            if(!($website == 'angoemprego.com')){
			    $TextoCandidatura = '';
			    $IAAplication = $this->GetOnContent($descricaoTratada);
			}
			
			if(($emprego->meta->_company_name == '') || ($emprego->meta->_company_name == null)){
			    $Empresa = 'Empresa em Angola';
			}
			
            //Inserir emprego no site Angola Recruta
            $client = new Client();

            try{
                
                //Testes para o AngolaEmprego
                $response2 = $client->request('POST', 'https://angolaemprego.com/api/job/create', ['verify' => false,
                    'json' => [
                        'title' => $IATitle,
                        'company' => $Empresa,
                        'location' => $emprego->meta->_job_location,
                        'description' => $descricaoTratada . $TextoCandidatura,
                        'email_or_link' => $IAAplication,
                        'image' => 'images/jobs/default.png',
                    ]
                ]);
                
                $dadosEmprego = json_decode($response2->getBody()->getContents(), true);
    
                //Adicionar novo Registro na tabela Link
                Link::create([
                    'url' => $emprego->link,
                    'country_id' => 1
                ]);

                
                //Publicar no Linkedin e Facebook***********
                $TextoEmprego = $this->DescricaoLimpa($descricaoTratada . $TextoCandidatura);
                $TextoEmprego = $TextoEmprego . "\n.\n https://angolaemprego.com/vagas/" . $dadosEmprego['slug'];
                $LinkEmprego = "https://angolaemprego.com/vagas/" . $dadosEmprego['slug'];
                // Defina e codifique o texto
                $text = $IATitle . "\n.\n" . $TextoEmprego . "\n.\nDeixe que nós façamos as candidaturas por si, fique em casa a relaxar que nós vamos enviar os seus curriculos para as melhores vagas durante uma semana: https://pay.kuenha.com/856ed35c-7b33-4e98-9352-954d22bc56a2\nCom base no seu CV, aplicamos automaticamente às vagas que combinam com o seu perfil\nMais Vagas em: https://angolaemprego.com/vagas/";
                
                //Facebook
                $this->PublicarFacebook2(
                    $IATitle . "\n.\n" . $TextoEmprego, 
                    $LinkEmprego, 
                    config('services.facebookapi.token') // SUBSTITUÍDO
                );
                
                //Linkedin
                $this->PublicarLinkedIn2(
                    $text, 
                    "https://angolaemprego.com/vagas/", 
                    "https://angolaemprego.com/storage/images/jobs/default.png", 
                    "99975145" // CORRIGIDO: Usando o Page ID diretamente como solicitado
                );
                
            } 
            catch(Exception $ex){
                // Lidar com a exceção, se necessário
            }
		}
	}

    public function PublicarFacebook2(string $message, string $link, string $access_token)
    {
        try {
            $apiUrl = 'https://graph.facebook.com/v18.0/me/feed';
            $client = new Client();
            $params = [
                'form_params' => [
                    'message' => $message,
                    'link' => $link,
                    'access_token' => $access_token,
                ],
            ];
            $response = $client->post($apiUrl, $params);
            return json_decode($response->getBody()->getContents(), true);
    
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
        
    public function PublicarLinkedIn2($post, $link = null, $imagePath = null, $_pageId = '99975145')
    {
        $accessToken = config('services.linkedinapi.token'); // SUBSTITUÍDO
        $pageId = $_pageId; // CORRIGIDO: Usa o ID passado como parâmetro ou o padrão '99975145'

        $client = new Client();

        try {
            $media = [];
            if ($imagePath) {
                $assetUrn = $this->uploadImageToLinkedIn($client, $accessToken, $pageId, $imagePath);
                if (!$assetUrn) {
                    return response()->json(['error' => 'Erro ao fazer o upload da imagem.'], 500);
                }
                $media[] = ["status" => "READY", "media" => $assetUrn];
            }

            $postContent = [
                "author" => "urn:li:organization:" . $pageId,
                "lifecycleState" => "PUBLISHED",
                "specificContent" => [
                    "com.linkedin.ugc.ShareContent" => [
                        "shareCommentary" => ["text" => $post],
                        "shareMediaCategory" => $imagePath ? "IMAGE" : "ARTICLE",
                        "media" => $imagePath ? $media : [["status" => "READY", "originalUrl" => $link]]
                    ]
                ],
                "visibility" => ["com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC"]
            ];

            $response = $client->post('https://api.linkedin.com/v2/ugcPosts', [
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ],
                'json' => $postContent,
            ]);

            if ($response->getStatusCode() == 201) {
                return response()->json(['message' => 'Postagem realizada com sucesso!'], 201);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao fazer a postagem: ' . $e->getMessage()], 500);
        }
    }

    private function uploadImageToLinkedIn($client, $accessToken, $pageId, $imagePath)
    {
        try {
            $registerResponse = $client->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
                'verify' => false,
                'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
                'json' => [
                    "registerUploadRequest" => [
                        "recipes" => ["urn:li:digitalmediaRecipe:feedshare-image"],
                        "owner" => "urn:li:organization:$pageId",
                        "serviceRelationships" => [["relationshipType" => "OWNER", "identifier" => "urn:li:userGeneratedContent"]],
                    ],
                ],
            ]);

            $registerData = json_decode($registerResponse->getBody(), true);
            $uploadUrl = $registerData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $assetUrn = $registerData['value']['asset'];

            $client->post($uploadUrl, [
                'verify' => false,
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                'body' => fopen($imagePath, 'r'),
            ]);

            return $assetUrn;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function DescricaoLimpa($Text)
    {
		$NovaDescricao = str_replace("<br>", "\n<br>", $Text);
		$NovaDescricao = str_replace(["</p>", "</h1>", "</h2>", "</h3>", "</li>"], ["</p>\n", "</h1>\n", "</h2>\n", "</h3>\n", "</li>\n"], $NovaDescricao);
		$NovaDescricao = explode('----------', $NovaDescricao)[0];
		$NovaDescricao = strip_tags($NovaDescricao);
		$NovaDescricao = str_replace("&nbsp;", "", $NovaDescricao);
		
		return $NovaDescricao;
    }
	
	function TituloViaGemini(){
        $api_key = config('services.geminiapi.token'); // SUBSTITUÍDO
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
        $client = new Client();

        $response = $client->post($url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'query' => [ 'key' => $api_key ],
            'json' => [
                "contents" => [["parts" => [["text" => "Fiz um artigo partilhando as vagas de emprego do dia de " . Carbon::now()->format('d-m-Y') . ". Crie um título chamativo para este artigo e no título deve conter o dia, mês e ano (O mês da data deve ser descrito) e me dê o dado no seguinte formato JSON: {title: TITULO_DO_ARTIGO} "]]]],
                "generationConfig" => ["temperature" => 0.9, "topK" => 1, "topP" => 1, "maxOutputTokens" => 2048, "stopSequences" => []],
                "safetySettings" => [["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"]],
            ],
        ]);

        $responseBody = $response->getBody()->getContents();
        $jsonObject = json_decode($responseBody);
        $PegarJSON = json_decode($jsonObject->candidates[0]->content->parts[0]->text);
        
        if(!isset($PegarJSON->title)) { throw ValidationException::withMessages(['Title não existe']); }
        
        try {
            return $PegarJSON->title;
        } catch (Exception $e) {
            return $this->TituloViaGemini();
        }
    }
    
    function DescricaoVagaViaGemini($Descricao){
        $api_key = config('services.geminiapi.token'); // SUBSTITUÍDO
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
        $client = new Client();

        $response = $client->request('POST', $url, ['verify' => false,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'query' => [ 'key' => $api_key ],
            'json' => [
                "contents" => [["parts" => [["text" => 'tenho o seguinte texto e pretendo transformar em linguagem de marcação (formataçaõ html) com bolds e titulos, etc para gravar como artigo na base de dados do meu blog, por favor faça isso e envie os dados no seguinte formato JSON: {description: DESCRICÃO_EM_HYPERTEXTO}: ' . $Descricao]]]],
                "generationConfig" => ["temperature" => 0.9, "topK" => 1, "topP" => 1, "stopSequences" => []],
                "safetySettings" => [["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"]],
            ],
        ]);

        $responseBody = $response->getBody()->getContents();
        $jsonObject = json_decode($responseBody);
        $jsonstring = strtr($jsonObject->candidates[0]->content->parts[0]->text, ['```' => "", 'json' => ""]);
        $PegarJSON = json_decode($jsonstring);

        if(!isset($PegarJSON->description)) { throw ValidationException::withMessages(['Descrição não existe']); }

        try {
            return $PegarJSON->description;
        } catch (Exception $e) {
            return $this->DescricaoVagaViaGemini($Descricao);
        }
    }
    
    function TituloVagaViaGemini($TituloAntigo = 'Administrador'){
        $api_key = config('services.geminiapi.token'); // SUBSTITUÍDO
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
        $client = new Client();

        $response = $client->request('POST', $url, ['verify' => false,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'query' => [ 'key' => $api_key ],
            'json' => [
                "contents" => [["parts" => [["text" => 'Crie um titulo para esta vaga de emprego: "' . $TituloAntigo . '". Os titulos nunca devem ser na primeira pessoa e devem ter o estilo parecido com: Vaga para xxx, precisa-se de, grande oportunidade para, opotunidade urgente, etc. Quero o dado no seguinte formato json: {title: TITULO_DA_VAGA}']]]],
                "generationConfig" => ["temperature" => 0.9, "topK" => 1, "topP" => 1, "stopSequences" => []],
                "safetySettings" => [["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"]],
            ],
        ]);

        $responseBody = $response->getBody()->getContents();
        $jsonObject = json_decode($responseBody);
        $jsonstring = strtr($jsonObject->candidates[0]->content->parts[0]->text, ['```' => "", 'json' => ""]);
        $PegarJSON = json_decode($jsonstring);

        if(!isset($PegarJSON->title)) { throw ValidationException::withMessages(['Title não existe']); }

        try {
            return $PegarJSON->title;
        } catch (Exception $e) {
            return $this->TituloVagaViaGemini($TituloAntigo);
        }
    }
    
    function GetOnContent($Content){
        $api_key = config('services.geminiapi.token'); // SUBSTITUÍDO
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
        $client = new Client();

        $response = $client->request('POST', $url, ['verify' => false,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'query' => [ 'key' => $api_key ],
            'json' => [
                "contents" => [["parts" => [["text" => 'olhe a descrição do seguinte emprego: ' . $Content . ' leia os dados e retire o endereço para aplicar à vaga (email ou link) e me dê os dados em json no seguinte formato  {email_or_link: EMAIL_OR _LINK}']]]],
                "generationConfig" => ["temperature" => 0.9, "topK" => 1, "topP" => 1, "stopSequences" => []],
                "safetySettings" => [["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"], ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"]],
            ],
        ]);

        $responseBody = $response->getBody()->getContents();
        $jsonObject = json_decode($responseBody);
        $jsonstring = strtr($jsonObject->candidates[0]->content->parts[0]->text, ['```' => "", 'json' => ""]);
        $PegarJSON = json_decode($jsonstring);

        if(!isset($PegarJSON->email_or_link)) { throw ValidationException::withMessages(['email_or_link não existe']); }

        try {
            return $PegarJSON->email_or_link;
        } catch (Exception $e) {
            return $this->GetOnContent($Content);
        }
    }
}