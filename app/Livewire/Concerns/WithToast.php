<?php

namespace App\Livewire\Concerns;

/** Toast kanan-atas lewat event browser 'toast' (resources/js/admin/toast.js). */
trait WithToast
{
    public function toast(string $type, string $title, string $text = ''): void
    {
        $this->dispatch('toast', type: $type, title: $title, text: $text);
    }
}
