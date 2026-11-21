<?php

namespace App\Http\Requests\TeacherAssignment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return false; // привязки не редактируются, только создаются/удаляются
    }

    public function rules(): array
    {
        return [];
    }
}
