<?php

namespace App\Presenters;

class RescuerResourceRequestPresenter
{
    public function categoryOptions(): array
    {
        return [
            ['key' => 'resource', 'label' => 'Resource'],
            ['key' => 'personnel', 'label' => 'Personnel'],
            ['key' => 'vehicle', 'label' => 'Vehicle / Transport'],
        ];
    }

    public function location(?string $description): string
    {
        if (! $description) {
            return 'Field location';
        }

        foreach (preg_split('/\r\n|\r|\n/', $description) as $line) {
            if (str_starts_with($line, 'Location: ')) {
                return trim(str_replace('Location: ', '', $line)) ?: 'Field location';
            }
        }

        return 'Field location';
    }
}


