<?php

namespace renslabs\LoggerDiscordChannel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallDiscordLoggerCommand extends Command
{
   /**
    * The name and signature of the console command.
    *
    * @var string
    */
   protected $signature = 'logger:discord-install {--force : Overwrite existing configuration}';

   /**
    * The console command description.
    *
    * @var string
    */
   protected $description = 'Install and configure Discord logging channel';

   /**
    * Execute the console command.
    */
   public function handle()
   {
      $this->info('🚀 Installing Discord Logger Package...');
      $this->newLine();

      if (config('logging.channels.discord') && !$this->option('force')) {
         $this->warn('⚠️  Discord channel already configured in logging.php');
         if (!$this->confirm('Do you want to continue anyway?')) {
            return 0;
         }
      }

      $this->addLoggingConfiguration();

      $this->addEnvironmentVariables();

      $this->showNextSteps();

      return 0;
   }

   private function addLoggingConfiguration()
   {
      $this->info('📝 Adding Discord channel to config/logging.php...');

      $loggingPath = config_path('logging.php');

      if (!file_exists($loggingPath)) {
         $this->error('❌ config/logging.php not found');
         return;
      }

      $content = file_get_contents($loggingPath);

      if (Str::contains($content, "'discord' =>")) {
         $this->warn('   ⚠️  Discord channel already exists in logging.php');
         return;
      }

      $discordConfig = "
        'discord' => [
            'driver' => 'custom',
            'via' => \\renslabs\\LoggerDiscordChannel\\DiscordLogger::class,
            'level' => env('DISCORD_LOG_LEVEL', 'error'),
            'webhook' => env('DISCORD_WEBHOOK_URL'),
            'message' => env('DISCORD_MESSAGE', null),
            'context' => env('DISCORD_INCLUDE_CONTEXT', false),
            'suffix' => env('DISCORD_LOG_SUFFIX', config('app.name')),
            'environment' => ['production', 'staging'],
        ],";

      $pattern = "/(\s+)'emergency' => \[/";
      $replacement = $discordConfig . "\n$1'emergency' => [";

      $newContent = preg_replace($pattern, $replacement, $content);

      if ($newContent !== $content) {
         file_put_contents($loggingPath, $newContent);
         $this->info('   ✅ Discord channel added to logging.php');
      } else {
         $this->error('   ❌ Failed to add Discord channel to logging.php');
         $this->info('   💡 Please add the configuration manually');
      }
   }

   private function addEnvironmentVariables()
   {
      $this->info('🔧 Adding environment variables to .env...');

      $envPath = base_path('.env');

      if (!file_exists($envPath)) {
         $this->warn('   ⚠️  .env file not found');
         return;
      }

      $content = file_get_contents($envPath);

      if (Str::contains($content, 'DISCORD_WEBHOOK_URL')) {
         $this->warn('   ⚠️  Discord environment variables already exist');
         return;
      }

      $discordEnv = "
# Discord Logger Configuration
DISCORD_WEBHOOK_URL=
DISCORD_LOG_LEVEL=info
DISCORD_LOG_SUFFIX=\"" . config('app.name') . "\"
DISCORD_INCLUDE_CONTEXT=true
DISCORD_MESSAGE=null";

      file_put_contents($envPath, $content . $discordEnv);
      $this->info('   ✅ Environment variables added to .env');
   }

   private function showNextSteps()
   {
      $this->newLine();
      $this->info('🎉 Discord Logger installation completed!');
      $this->newLine();

      $this->info('📋 Next Steps:');
      $this->info('1. Create a Discord webhook in your Discord server');
      $this->info('2. Copy the webhook URL and set it in your .env file:');
      $this->line('   DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/...');
      $this->newLine();

      $this->info('3. Test the configuration:');
      $this->line('   php artisan logger:discord-status');
      $this->line('   php artisan logger:discord-test');
      $this->newLine();

      $this->info('4. Use in your code:');
      $this->line('   Log::error(\'Something went wrong!\');');
      $this->newLine();

      $this->info('📚 For more information, check the documentation.');
   }
}
