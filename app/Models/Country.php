<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * País de uma vaga — a mesma tabela do portal (angola-emprego).
 *
 * O "code" é o ISO 3166-1 alfa-2 (AO, BR, ES...) e é por ele que se identificam
 * as vagas de cada país, porque o id depende da ordem em que os países foram
 * inseridos.
 */
class Country extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'name_en', 'code'];

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }
}
