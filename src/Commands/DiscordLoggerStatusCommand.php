<?php

namespace renslabs\LoggerDiscordChannel\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class DiscordLoggerStatusCommand extends Command
{
   /**
    * The name and signature of the console command.
    *
    * @var string
    */
   protected $signature = 'logger:discord-status';

   /**
    * The console command description.
    *l
    * @var string
    */
   protected $description = 'Check Discord logging channel status and configuration';

   /**
    * Execute the console command.
    */
   public function handle()
   {
      $this->info('🔍 Discord Logger Package Status Check');
      $this->info('=====================================');
      $this->newLine();

      $status = true;

      $this->checkPackageInstallation($status);

      $this->checkConfiguration($status);

      $this->checkWebhookConnectivity($status);

      $this->checkEnvironmentSettings($status);

      $this->newLine();

      if ($status) {
         $this->info('🎉 All checks passed! Discord Logger is ready to use.');
         $this->info('💡 Run "php artisan logger:discord-test" to send a test message.');
      } else {
         $this->error('❌ Some issues were found. Please fix them before using Discord Logger.');
      }

      return $status ? 0 : 1;
   }

   private function checkPackageInstallation(&$status)
   {
      $this->info('1. 📦 Package Installation Check');

      if (class_exists('renslabs\LoggerDiscordChannel\DiscordLogger')) {
         $this->info('   ✅ DiscordLogger class found');
      } else {
         $this->error('   ❌ DiscordLogger class not found');
         $status = false;
      }

      if (class_exists('renslabs\LoggerDiscordChannel\DiscordHandler')) {
         $this->info('   ✅ DiscordHandler class found');
      } else {
         $this->error('   ❌ DiscordHandler class not found');
         $status = false;
      }

      if (class_exists('GuzzleHttp\Client')) {
         $this->info('   ✅ GuzzleHttp client available');
      } else {
         $this->error('   ❌ GuzzleHttp client not found');
         $status = false;
      }

      $this->newLine();
   }

   private function checkConfiguration(&$status)
   {
      $this->info('2. ⚙️  Configuration Check');

      $discordConfig = config('logging.channels.discord');
      if ($discordConfig) {
         $this->info('   ✅ Discord channel configured in logging.php');

         if (isset($discordConfig['driver']) && $discordConfig['driver'] === 'custom') {
            $this->info('   ✅ Driver set to "custom"');
         } else {
            $this->error('   ❌ Driver should be set to "custom"');
            $this->info('   💡 Add this to config/logging.php channels:');
            $this->line('   \'discord\' => [');
            $this->line('       \'driver\' => \'custom\',');
            $this->line('       \'via\' => \\renslabs\\LoggerDiscordChannel\\DiscordLogger::class,');
            $this->line('       // ... other config');
            $this->line('   ],');
            $status = false;
         }

         if (isset($discordConfig['via']) && $discordConfig['via'] === 'renslabs\LoggerDiscordChannel\DiscordLogger') {
            $this->info('   ✅ Via parameter correctly set');
         } else {
            $this->error('   ❌ Via parameter not set correctly');
            $status = false;
         }

         if (isset($discordConfig['webhook']) && !empty($discordConfig['webhook'])) {
            $this->info('   ✅ Webhook URL configured');
         } else {
            $this->error('   ❌ Webhook URL not configured');
            $this->info('   💡 Set DISCORD_WEBHOOK_URL in your .env file');
            $status = false;
         }

         $level = $discordConfig['level'] ?? 'debug';
         $this->info("   ℹ️  Log level: {$level}");
      } else {
         $this->error('   ❌ Discord channel not configured in logging.php');
         $this->info('   💡 Run "php artisan logger:discord-install" to auto-configure');
         $status = false;
      }

      $this->newLine();
   }

   private function checkWebhookConnectivity(&$status)
   {
      $this->info('3. 🌐 Webhook Connectivity Check');

      $webhook = config('logging.channels.discord.webhook');
      if (!$webhook) {
         $this->error('   ❌ No webhook URL to test');
         $status = false;
         $this->newLine();
         return;
      }

      try {
         $client = new Client(['timeout' => 10]);

         $testPayload = [
            'content' => '🏓 Discord Logger connectivity test - ' . now()->format('Y-m-d H:i:s')
         ];

         $response = $client->post($webhook, [
            'json' => $testPayload,
            'headers' => [
               'Content-Type' => 'application/json'
            ]
         ]);

         if ($response->getStatusCode() === 204) {
            $this->info('   ✅ Webhook is reachable and working');
            $this->info('   📱 Check your Discord channel for the test message');
         } else {
            $this->warn('   ⚠️  Webhook responded with status: ' . $response->getStatusCode());
         }
      } catch (RequestException $e) {
         $this->error('   ❌ Webhook connectivity failed: ' . $e->getMessage());
         $status = false;
      } catch (\Exception $e) {
         $this->error('   ❌ Unexpected error: ' . $e->getMessage());
         $status = false;
      }

      $this->newLine();
   }

   private function checkEnvironmentSettings(&$status)
   {
      $this->info('4. 🌍 Environment Settings Check');

      $environment = config('logging.channels.discord.environment', ['production']);
      $currentEnv = app()->environment();

      $this->info("   ℹ️  Current environment: {$currentEnv}");
      $this->info("   ℹ️  Configured environments: " . implode(', ', (array)$environment));

      if (in_array($currentEnv, (array)$environment)) {
         $this->info('   ✅ Discord logging is enabled for current environment');
      } else {
         $this->warn('   ⚠️  Discord logging is disabled for current environment');
         $this->info('   💡 This is normal for development environments');
      }

      $this->newLine();
   }
}
