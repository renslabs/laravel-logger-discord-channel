<?php

namespace renslabs\LoggerDiscordChannel;

use Illuminate\Support\ServiceProvider;
use renslabs\LoggerDiscordChannel\Commands\DiscordLoggerStatusCommand;
use renslabs\LoggerDiscordChannel\Commands\TestDiscordLoggerCommand;
use renslabs\LoggerDiscordChannel\Commands\InstallDiscordLoggerCommand;

class DiscordLoggerServiceProvider extends ServiceProvider
{
   /**
    * Register services.
    */
   public function register(): void
   {
      //
   }

   /**
    * Bootstrap services.
    */
   public function boot(): void
   {
      if ($this->app->runningInConsole()) {
         $this->commands([
            DiscordLoggerStatusCommand::class,
            TestDiscordLoggerCommand::class,
            InstallDiscordLoggerCommand::class,
         ]);
      }
   }
}
