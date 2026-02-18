<?php

namespace App\Http\Controllers;

use App\Models\AutoApplication;
use Illuminate\Http\Request;

use App\Models\TrackedJob;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class AutoApplicationController extends Controller
{
    public function fetchAndMatchJobs()
    {
        $response = Http::get('https://ao.empregosyoyota.net/api/jobs');

        if ($response->successful()) {
            $data = $response->json();
            $jobs = $data['data']['data'] ?? [];

            // Obter utilizadores com subscrição ativa
            $users = User::where('subscription_status', 'active')
                ->where('subscription_end', '>', now())
                ->with('categories')
                ->get();

            $matchesCount = 0;

            foreach ($jobs as $jobData) {
                // Normalizar categorias do emprego (usando o nome)
                $jobCategoryNames = collect($jobData['categories'])->pluck('name')->map(fn($name) => strtolower(trim($name)))->all();

                // Filtrar utilizadores interessados
                $interestedUsers = $users->filter(function ($user) use ($jobCategoryNames) {
                    $userCategoryNames = $user->categories->pluck('name')->map(fn($name) => strtolower(trim($name)))->all();
                    return !empty(array_intersect($jobCategoryNames, $userCategoryNames));
                });

                if ($interestedUsers->isNotEmpty()) {
                    // Criar ou obter o TrackedJob
                    $trackedJob = TrackedJob::firstOrCreate(
                        [
                            'provider' => 'yoyota',
                            'provider_job_id' => $jobData['id'],
                        ],
                        [
                            'job_title' => $jobData['title'],
                            'apply_email' => $jobData['email_or_link'],
                        ]
                    );

                    // Criar AutoApplication para cada utilizador interessado
                    foreach ($interestedUsers as $user) {
                        try {
                            AutoApplication::firstOrCreate(
                                [
                                    'user_id' => $user->id,
                                    'tracked_job_id' => $trackedJob->id,
                                ],
                                [
                                    'status' => 'pending',
                                    'error_message' => null,
                                ]
                            );
                            $matchesCount++;
                        } catch (\Exception $e) {
                            // Ignorar duplicados ou erros pontuais
                            continue;
                        }
                    }
                }
            }

            return response()->json([
                'message' => 'Jobs processed successfully.',
                'matches_created' => $matchesCount
            ]);
        }

        return response()->json(['message' => 'Failed to fetch jobs from API.'], 500);
    }

    public function send(AutoApplication $autoApplication)
    {
        $autoApplication->update(['status' => 'sent']);
        return redirect()->back()->with('success', 'Application marked as sent.');
    }

    public function markAsFailed(AutoApplication $autoApplication)
    {
        $autoApplication->update(['status' => 'failed']);
        return redirect()->back()->with('success', 'Application marked as failed.');
    }

    public function bulkUpdate(Request $request)
    {
        $action = $request->action;

        // Verificar se é uma ação individual (send_ID ou fail_ID)
        if (\Illuminate\Support\Str::contains($action, '_')) {
            $parts = explode('_', $action);
            $type = $parts[0];
            $id = $parts[1];
            
            $app = AutoApplication::find($id);

            if ($app) {
                // Se for envio, vamos guardar o Assunto e Mensagem personalizados e enviar o email
                if ($type === 'send') {
                    $subject = null;
                    $message = null;
                    if ($request->has("applications.{$id}")) {
                        $data = $request->input("applications.{$id}");
                        $subject = $data['subject'] ?? null;
                        $message = $data['message'] ?? null;
                    }

                    $cvBase64 = $request->input('cv_base64');
                    $cvMime = $request->input('cv_mime', 'application/pdf');

                    $result = $this->sendEmail($app, $subject, $message, $cvBase64, $cvMime);

                    if ($result['success']) {
                        if ($request->wantsJson()) {
                            return response()->json(['success' => true, 'message' => 'Application sent successfully via Maileroo.']);
                        }
                        return redirect()->back()->with('success', 'Application sent successfully via Maileroo.');
                    } else {
                        if ($request->wantsJson()) {
                            return response()->json(['success' => false, 'error' => $result['error']], 500);
                        }
                        return redirect()->back()->with('error', 'Failed to send email: ' . $result['error']);
                    }
                } elseif ($type === 'fail') {
                    $app->status = 'failed';
                    $app->save();
                    if ($request->wantsJson()) {
                        return response()->json(['success' => true, 'message' => 'Application marked as failed.']);
                    }
                    return redirect()->back()->with('success', 'Application marked as failed.');
                }
            }
        } 
        
        // Ação em massa
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:auto_applications,id',
            'action' => 'required|in:send,fail',
        ]);

        $status = $request->action === 'send' ? 'sent' : 'failed';

        // Para ação em massa, podemos também querer salvar os dados, mas assumindo os defaults do form ou ignorando para simplificar. 
        // Se quisermos salvar, teríamos que iterar. Vamos iterar para salvar se for send.
        
        $ids = $request->ids;
        if ($status === 'sent') {
            foreach($ids as $id) {
                 $app = AutoApplication::find($id);
                 if ($request->has("applications.{$id}")) {
                    $data = $request->input("applications.{$id}");
                    $app->subject = $data['subject'] ?? null;
                    $app->message = $data['message'] ?? null;
                 }
                 // TODO: Implement bulk email sending here if needed
                 $app->status = 'sent';
                 $app->save();
            }
        } else {
             AutoApplication::whereIn('id', $ids)->update(['status' => $status]);
        }

        return redirect()->back()->with('success', 'Selected applications updated.');
    }

    private function sendEmail(AutoApplication $app, $subject = null, $message = null, $cvBase64 = null, $cvMime = 'application/pdf')
    {
        // Atualizar assunto/mensagem no modelo antes de enviar, se fornecidos
        if ($subject) $app->subject = $subject;
        if ($message) $app->message = $message;
        
        // Se CV não fornecido via argumento (ex: bulk send), tentar obter do perfil do usuário
        if (!$cvBase64 && $app->user->cv_path) {
            try {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($app->user->cv_path)) {
                    $fileContent = \Illuminate\Support\Facades\Storage::disk('public')->get($app->user->cv_path);
                    $cvBase64 = base64_encode($fileContent);
                    $cvMime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($app->user->cv_path);
                }
            } catch (\Exception $e) {
                // Log failure to read CV
            }
        }

        // Enviar Email via Maileroo
        $payload = [
            "from" => [
                "address" => \Illuminate\Support\Str::slug($app->user->name, '.') . '@angolaemprego.com',
                "display_name" => $app->user->name
            ],
            "to" => [
               [
                   "address" => $app->trackedJob->apply_email
               ]
            ],
            "cc" => [
                "address" => $app->user->email,
                 "display_name" => $app->user->name
            ],
            "subject" => $app->subject ?? "Candidatura - {$app->trackedJob->job_title}",
            "html" => nl2br($app->message ?? "Prezados,\n\nGostaria de submeter a minha candidatura."),
            "tracking" => true
        ];

        // Adicionar anexo se existir
        if ($cvBase64) {
            $payload['attachments'] = [
                [
                    "file_name" => "Curriculo_" . \Illuminate\Support\Str::slug($app->user->name) . ".pdf", 
                    "content_type" => $cvMime,
                    "content" => $cvBase64,
                    "inline" => false
                ]
            ];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-API-Key' => env('MAILEROO_API_KEY'),
                'Content-Type' => 'application/json'
            ])->post('https://smtp.maileroo.com/api/v2/emails', $payload);

            if ($response->successful()) {
                $app->status = 'sent';
                $app->save();
                return ['success' => true];
            } else {
                $app->error_message = $response->body();
                $app->status = 'failed';
                $app->save();
                return ['success' => false, 'error' => $response->body()];
            }
        } catch (\Exception $e) {
             $app->error_message = $e->getMessage();
             $app->status = 'failed';
             $app->save();
             return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
