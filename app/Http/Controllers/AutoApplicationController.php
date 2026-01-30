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
                    if ($request->has("applications.{$id}")) {
                        $data = $request->input("applications.{$id}");
                        $app->subject = $data['subject'] ?? null;
                        $app->message = $data['message'] ?? null;
                    }
                    
                    // Enviar Email via Maileroo
                    $payload = [
                        "from" => [
                            "address" => "rosa.barbosa@angolaemprego.com",
                            "display_name" => "Rosa Barbosa"
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
                    if ($request->has('cv_base64')) {
                        $base64Content = $request->input('cv_base64');
                        $mimeType = $request->input('cv_mime', 'application/pdf');
                        
                        // Validar se é base64 válido (opcional, mas bom)
                        // Apenas adicionar ao payload
                        $payload['attachments'] = [
                            [
                                "file_name" => "Curriculo_" . \Illuminate\Support\Str::slug($app->user->name) . ".pdf", // Assumindo PDF ou usar extensão correta se possível
                                "content_type" => $mimeType,
                                "content" => $base64Content,
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
                            return redirect()->back()->with('success', 'Application sent successfully via Maileroo.');
                        } else {
                            $app->error_message = $response->body();
                            $app->status = 'failed';
                            $app->save();
                            return redirect()->back()->with('error', 'Failed to send email: ' . $response->body());
                        }
                    } catch (\Exception $e) {
                         $app->error_message = $e->getMessage();
                         $app->status = 'failed';
                         $app->save();
                         return redirect()->back()->with('error', 'Exception sending email: ' . $e->getMessage());
                    }
                } elseif ($type === 'fail') {
                    $app->status = 'failed';
                    $app->save();
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
}
