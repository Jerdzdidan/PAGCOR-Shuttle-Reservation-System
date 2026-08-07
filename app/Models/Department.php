<?php

namespace App\Models;

use App\Models\Concerns\RecordsUserActivity;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, RecordsUserActivity;

    /** @var list<string> */
    protected $fillable = [
        'name',
    ];

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department', 'name');
    }
}
