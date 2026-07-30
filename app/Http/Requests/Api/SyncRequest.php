<?php

namespace App\Http\Requests\Api;

use App\Support\PlateCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRequest extends FormRequest
{
    private const MAX_BATCH = 400;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'revision' => ['nullable', 'string', 'max:32'],
            'messages_since' => ['nullable', 'integer', 'min:0'],

            'add' => ['array', 'max:'.self::MAX_BATCH],
            'add.*.uid' => ['required', 'uuid'],
            'add.*.code' => ['required', Rule::in(PlateCatalogue::codes())],
            'add.*.car' => ['required', 'integer', Rule::in([1, 2])],
            'add.*.variant' => ['required', 'integer', 'between:0,5'],
            'add.*.x' => ['required', 'integer', 'between:0,100'],
            'add.*.y' => ['required', 'integer', 'between:0,100'],
            'add.*.rotation' => ['required', 'integer', 'between:-180,180'],
            'add.*.scale' => ['required', 'integer', 'between:50,180'],

            'remove' => ['array', 'max:'.self::MAX_BATCH],
            'remove.*' => ['required', 'uuid'],

            'messages' => ['array', 'max:50'],
            'messages.*.uid' => ['required', 'uuid'],
            'messages.*.car' => ['required', 'integer', Rule::in([1, 2])],
            'messages.*.body' => ['required', 'string', 'max:240'],
        ];
    }

    public function clientRevision(): string
    {
        return (string) $this->validated('revision', '');
    }

    public function messagesSince(): int
    {
        return (int) $this->validated('messages_since', 0);
    }

    /**
     * @return list<array{uid: string, code: string, car: int, variant: int, x: int, y: int, rotation: int, scale: int}>
     */
    public function addedSightings(): array
    {
        /** @var list<array{uid: string, code: string, car: int, variant: int, x: int, y: int, rotation: int, scale: int}> $added */
        $added = array_values($this->validated('add', []));

        return $added;
    }

    /**
     * @return list<string>
     */
    public function removedSightingUids(): array
    {
        /** @var list<string> $removed */
        $removed = array_values(array_unique($this->validated('remove', [])));

        return $removed;
    }

    /**
     * @return list<array{uid: string, car: int, body: string}>
     */
    public function postedMessages(): array
    {
        /** @var list<array{uid: string, car: int, body: string}> $messages */
        $messages = array_values($this->validated('messages', []));

        return $messages;
    }

    protected function prepareForValidation(): void
    {
        $messages = $this->input('messages');

        if (! is_array($messages)) {
            return;
        }

        $this->merge([
            'messages' => array_values(array_filter(
                array_map(
                    static function (mixed $message): mixed {
                        if (is_array($message) && is_string($message['body'] ?? null)) {
                            $message['body'] = trim($message['body']);
                        }

                        return $message;
                    },
                    $messages,
                ),
                static fn (mixed $message): bool => ! is_array($message) || ($message['body'] ?? '') !== '',
            )),
        ]);
    }
}
