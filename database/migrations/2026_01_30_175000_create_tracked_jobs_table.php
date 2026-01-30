<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrackedJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('tracked_jobs')) {
            Schema::create('tracked_jobs', function (Blueprint $table) {
                $table->id();
        
                // Identificação Única da Fonte
                $table->string('provider', 50)->index(); // 'internal', 'linkedin', etc.
                $table->string('provider_job_id', 100)->index(); // ID original na fonte
        
                // Dados da Vaga (Snapshot)
                $table->string('job_title');
                $table->string('apply_email'); 
                // Podes adicionar mais campos aqui (descrição, salário, etc.)
                
                $table->timestamps();
        
                // Garante que não crias a mesma vaga duas vezes
                $table->unique(['provider', 'provider_job_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tracked_jobs');
    }
}
