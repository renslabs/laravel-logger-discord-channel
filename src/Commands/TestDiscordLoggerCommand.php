<?php

namespace renslabs\LoggerDiscordChannel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestDiscordLoggerCommand extends Command
{
   /**
    * The name and signature of the console command.
    *
    * @var string
    */
   protected $signature = 'logger:discord-test {--level=info : Log level to test (debug, info, warning, error)}';

   /**
    * The console command description.
    *
    * @var string
    */
   protected $description = 'Test Discord logging channel functionality';

   /**
    * Execute the console command.
    */
   public function handle()
   {
      $level = $this->option('level');

      $this->info('Testing Discord Logger Package...');
      $this->newLine();

      if (!config('logging.channels.discord')) {
         $this->error('❌ Discord logging channel is not configured in config/logging.php');
         $this->info('💡 Run "php artisan logger:discord-install" to auto-configure');
         return 1;
      }

      $webhook = config('logging.channels.discord.webhook');
      if (!$webhook) {
         $this->error('❌ Discord webhook URL is not configured');
         $this->info('💡 Set DISCORD_WEBHOOK_URL in your .env file');
         return 1;
      }

      $this->info('✅ Discord logging configuration found');
      $this->info("📡 Webhook URL: " . substr($webhook, 0, 50) . '...');
      $this->newLine();

      $testMessage = 'Discord Logger Test - ' . now()->format('Y-m-d H:i:s');

      try {
         $this->info("🧪 Testing log level: {$level}");

         switch ($level) {
            case 'debug':
               Log::debug($testMessage, [
                  'test' => true,
                  'userId' => 'test-user',
                  'command' => 'logger:discord-test'
               ]);
               break;
            case 'info':
               Log::info($testMessage, [
                  'test' => true,
                  'userId' => 'test-user',
                  'command' => 'logger:discord-test'
               ]);
               break;
            case 'warning':
               Log::warning($testMessage, [
                  'test' => true,
                  'userId' => 'test-user',
                  'command' => 'logger:discord-test'
               ]);
               break;
            case 'error':
               Log::error($testMessage, [
                  'test' => true,
                  'userId' => 'test-user',
                  'command' => 'logger:discord-test'
               ]);
               break;
            case 'notice':
               Log::notice($testMessage, [
                  'test' => true,
                  'userId' => 'test-user',
                  'command' => 'logger:discord-test'
               ]);
               break;
            case 'critical':
               Log::critical($testMessage, [
                  'test' => true,
                  'userId' => 'test-user',
                  'command' => 'logger:discord-test'
               ]);
               break;
            case 'alert':
               Log::alert($testMessage, [
                  'test' => true,
                  'userId' => 'test-user',
                  'command' => 'logger:discord-test'
               ]);
               break;
            case 'emergency':
               Log::emergency($testMessage, [
                  'test' => true,
                  'userId' => 'test-user',
                  'command' => 'logger:discord-test'
               ]);
               break;
            default:
               $this->error("Invalid log level: {$level}");
               $this->info('Valid levels: debug, info, warning, error');
               return 1;
         }

         $this->info('✅ Log message sent successfully!');
         $this->info('📱 Check your Discord channel to see if the message was received.');
      } catch (\Exception $e) {
         $this->error('❌ Failed to send log message to Discord');
         $this->error('Error: ' . $e->getMessage());
         return 1;
      }

      $this->newLine();
      $this->info('🎉 Discord Logger test completed!');
      $this->info('💡 Run "php artisan logger:discord-status" to check package status');

      return 0;
   }
}
