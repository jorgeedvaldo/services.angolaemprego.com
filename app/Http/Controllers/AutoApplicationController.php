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
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:auto_applications,id',
            'action' => 'required|in:send,fail',
        ]);

        $status = $request->action === 'send' ? 'sent' : 'failed';

        AutoApplication::whereIn('id', $request->ids)->update(['status' => $status]);

        return redirect()->back()->with('success', 'Selected applications updated.');
    }
}
