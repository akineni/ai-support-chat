<?php

namespace App\Providers;

use App\Repositories\Contracts\AttachmentRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Eloquent\AttachmentRepository;
use App\Repositories\Eloquent\ConversationRepository;
use App\Repositories\Eloquent\MessageRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
        $this->app->bind(AttachmentRepositoryInterface::class, AttachmentRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
