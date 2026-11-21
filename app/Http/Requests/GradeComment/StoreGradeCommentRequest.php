<?php

namespace App\Http\Requests\GradeComment;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!$this->user()?->isTeacher()) {
            return false;
        }

        $grade = $this->route('grade');

        if (!$grade) {
            return false;
        }

        // Только к своей оценке
        if ($grade->teacher_id !== $this->user()->teacher?->id) {
            return false;
        }

        // В закрытом периоде комментарии не редактируются
        return !$grade->period?->isClosed();
    }

    public function rules(): array
    {
        return [
            'comment_text' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment_text.required' => 'Текст комментария обязателен.',
            'comment_text.max' => 'Комментарий не может быть длиннее 2000 символов.',
        ];
    }
}
