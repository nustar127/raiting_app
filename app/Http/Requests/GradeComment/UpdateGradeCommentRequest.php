<?php

namespace App\Http\Requests\GradeComment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradeCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!$this->user()?->isTeacher()) {
            return false;
        }

        $comment = $this->route('comment');

        if (!$comment) {
            return false;
        }

        if ($comment->teacher_id !== $this->user()->teacher?->id) {
            return false;
        }

        return !$comment->grade?->period?->isClosed();
    }

    public function rules(): array
    {
        return [
            'comment_text' => ['sometimes', 'required', 'string', 'max:2000'],
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
