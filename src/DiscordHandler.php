<?php

namespace renslabs\LoggerDiscordChannel;

use GuzzleHttp\RequestOptions;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DiscordHandler extends AbstractProcessingHandler
{
    private $guzzle;
    private $suffix;
    private $webhook;
    private $message;
    private $context;
    private $maxFieldLength;
    private $maxDescriptionLength;

    /**
     * Discord Handler constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        $this->suffix = $config['suffix'] ?? config('app.name', 'Laravel');
        $this->guzzle = new \GuzzleHttp\Client(['timeout' => 10]);
        $this->webhook = $config['webhook'] ?? false;
        $this->message = $config['message'] ?? null;
        $this->context = $config['context'] ?? false;
        $this->maxFieldLength = $config['max_field_length'] ?? 1024;
        $this->maxDescriptionLength = $config['max_description_length'] ?? 4000;

        parent::__construct($config['level'] ?? 'debug', $this->bubble);
    }

    /**
     * Write log record to Discord
     * @param LogRecord $record
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function write(LogRecord $record): void
    {
        if (!$this->webhook) {
            return;
        }

        try {
            $discordPayload = $this->buildDiscordPayload($record);

            $this->guzzle->request('POST', $this->webhook, [
                RequestOptions::JSON => $discordPayload,
                RequestOptions::HEADERS => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Laravel-Discord-Logger/1.0'
                ]
            ]);
        } catch (\Exception $e) {
            if (config('app.debug')) {
                error_log("Discord Logger Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Build Discord webhook payload
     */
    private function buildDiscordPayload(LogRecord $record): array
    {
        $logInfo = $this->extractLogInfo($record);
        $fields = $this->buildFields($record);
        $embeds = $this->buildEmbeds($record, $logInfo, $fields);

        $payload = [
            'embeds' => $embeds
        ];

        if ($this->message) {
            $payload['content'] = $this->message;
        }

        return $payload;
    }

    /**
     * Extract log level information
     */
    private function extractLogInfo(LogRecord $record): array
    {
        $level = $record->level;
        $levelName = $level->getName();
        $levelPsr = $level->toPsrLogLevel();

        return [
            'level' => $level,
            'level_name' => $levelName,
            'level_psr' => $levelPsr,
            'emoji' => $this->getEmojiForLevel($levelPsr),
            'color' => $this->getColorForLevel($levelPsr)
        ];
    }

    /**
     * Get emoji for log level
     */
    private function getEmojiForLevel(string $level): string
    {
        return match ($level) {
            LogLevel::EMERGENCY => '🚨',
            LogLevel::ALERT => '🔴',
            LogLevel::CRITICAL => '💥',
            LogLevel::ERROR => '❌',
            LogLevel::WARNING => '⚠️',
            LogLevel::NOTICE => '🔔',
            LogLevel::INFO => 'ℹ️',
            LogLevel::DEBUG => '🔍',
            default => '📝'
        };
    }

    /**
     * Get color for log level
     */
    private function getColorForLevel(string $level): int
    {
        return match ($level) {
            LogLevel::EMERGENCY => 0x8B0000, // Dark Red
            LogLevel::ALERT => 0xFF0000,     // Red
            LogLevel::CRITICAL => 0xFF4500,  // Orange Red
            LogLevel::ERROR => 0xE74C3C,     // Red
            LogLevel::WARNING => 0xF39C12,   // Orange
            LogLevel::NOTICE => 0x3498DB,    // Blue
            LogLevel::INFO => 0x2ECC71,      // Green
            LogLevel::DEBUG => 0x95A5A6,     // Gray
            default => 0x34495E              // Dark Gray
        };
    }

    /**
     * Build Discord embed fields
     */
    private function buildFields(LogRecord $record): array
    {
        $fields = [];
        $context = $record->context;
        $extra = $record->extra;

        $fields[] = [
            'name' => '🌍 Environment',
            'value' => app()->environment(),
            'inline' => true
        ];

        $fields[] = [
            'name' => '⏰ Timestamp',
            'value' => $record->datetime->format('Y-m-d H:i:s T'),
            'inline' => true
        ];

        // Channel
        $fields[] = [
            'name' => '📡 Channel',
            'value' => $record->channel,
            'inline' => true
        ];

        if (!app()->runningInConsole()) {
            $this->addRequestFields($fields);
        }

        $this->addUserFields($fields, $context);

        $this->addExceptionFields($fields, $context);

        $this->addContextFields($fields, $context);

        $this->addExtraFields($fields, $extra);

        $fields[] = [
            'name' => '💾 Memory Usage',
            'value' => $this->formatBytes(memory_get_usage(true)),
            'inline' => true
        ];

        return array_slice($fields, 0, 25);
    }

    /**
     * Add request-related fields
     */
    private function addRequestFields(array &$fields): void
    {
        $request = request();

        if ($request) {
            if ($request->fullUrl()) {
                $fields[] = [
                    'name' => '🌐 URL',
                    'value' => Str::limit($request->fullUrl(), $this->maxFieldLength),
                    'inline' => false
                ];
            }

            $fields[] = [
                'name' => '📋 Method',
                'value' => $request->method(),
                'inline' => true
            ];

            $fields[] = [
                'name' => '🌍 IP Address',
                'value' => $request->ip(),
                'inline' => true
            ];

            if ($request->userAgent()) {
                $fields[] = [
                    'name' => '🖥️ User Agent',
                    'value' => Str::limit($request->userAgent(), $this->maxFieldLength),
                    'inline' => false
                ];
            }

            if ($request->header('referer')) {
                $fields[] = [
                    'name' => '🔗 Referer',
                    'value' => Str::limit($request->header('referer'), $this->maxFieldLength),
                    'inline' => false
                ];
            }
        }
    }

    /**
     * Add user-related fields
     */
    private function addUserFields(array &$fields, array $context): void
    {
        if (isset($context['userId'])) {
            $fields[] = [
                'name' => '👤 User ID',
                'value' => $context['userId'],
                'inline' => true
            ];
        }

        if (auth()->check()) {
            $user = auth()->user();
            $fields[] = [
                'name' => '🔐 Authenticated User',
                'value' => $user->id . ($user->email ? " ({$user->email})" : ''),
                'inline' => true
            ];
        }
    }

    /**
     * Add exception-related fields
     */
    private function addExceptionFields(array &$fields, array $context): void
    {
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $exception = $context['exception'];

            $fields[] = [
                'name' => '💥 Exception',
                'value' => get_class($exception),
                'inline' => true
            ];

            $fields[] = [
                'name' => '📁 File',
                'value' => '`' . Str::after($exception->getFile(), base_path()) . ':' . $exception->getLine() . '`',
                'inline' => false
            ];

            if ($exception->getCode()) {
                $fields[] = [
                    'name' => '🔢 Code',
                    'value' => $exception->getCode(),
                    'inline' => true
                ];
            }

            if ($exception->getPrevious()) {
                $fields[] = [
                    'name' => '🔗 Previous Exception',
                    'value' => get_class($exception->getPrevious()),
                    'inline' => true
                ];
            }
        }
    }

    /**
     * Add custom context fields
     */
    private function addContextFields(array &$fields, array $context): void
    {
        $excludeKeys = ['exception', 'userId'];

        foreach ($context as $key => $value) {
            if (in_array($key, $excludeKeys) || count($fields) >= 23) {
                continue;
            }

            if (is_scalar($value) || is_null($value)) {
                $fields[] = [
                    'name' => "📋 {$key}",
                    'value' => Str::limit((string) $value, $this->maxFieldLength),
                    'inline' => true
                ];
            } elseif (is_array($value) || is_object($value)) {
                $fields[] = [
                    'name' => "📋 {$key}",
                    'value' => '```json' . "\n" . Str::limit(json_encode($value, JSON_PRETTY_PRINT), $this->maxFieldLength - 10) . "\n" . '```',
                    'inline' => false
                ];
            }
        }
    }

    /**
     * Add extra fields
     */
    private function addExtraFields(array &$fields, array $extra): void
    {
        foreach ($extra as $key => $value) {
            if (count($fields) >= 24) {
                break;
            }

            if (is_scalar($value) || is_null($value)) {
                $fields[] = [
                    'name' => "⚙️ {$key}",
                    'value' => Str::limit((string) $value, $this->maxFieldLength),
                    'inline' => true
                ];
            }
        }
    }

    /**
     * Build Discord embeds
     */
    private function buildEmbeds(LogRecord $record, array $logInfo, array $fields): array
    {
        $embeds = [];

        $mainEmbed = [
            'title' => $this->buildTitle($logInfo),
            'description' => $this->buildDescription($record),
            'color' => $logInfo['color'],
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.v\Z'),
            'fields' => $fields,
            'footer' => [
                'text' => $this->suffix . ' • Laravel Discord Logger',
                'icon_url' => 'https://laravel.com/img/favicon/favicon-32x32.png'
            ]
        ];

        $embeds[] = $mainEmbed;

        if ($this->context && !empty($record->context)) {
            $contextEmbed = $this->buildContextEmbed($record);
            if ($contextEmbed) {
                $embeds[] = $contextEmbed;
            }
        }

        return $embeds;
    }

    /**
     * Build embed title
     */
    private function buildTitle(array $logInfo): string
    {
        return sprintf(
            '%s %s Log',
            $logInfo['emoji'],
            ucfirst(strtolower($logInfo['level_name']))
        );
    }

    /**
     * Build embed description
     */
    private function buildDescription(LogRecord $record): string
    {
        $message = $record->message;

        $description = "```\n" . Str::limit($message, $this->maxDescriptionLength - 10) . "\n```";

        return $description;
    }

    /**
     * Build context embed
     */
    private function buildContextEmbed(LogRecord $record): ?array
    {
        $context = $record->context;
        $extra = $record->extra;

        if (empty($context) && empty($extra)) {
            return null;
        }

        $contextData = [];

        if (!empty($context)) {
            $contextData['Context'] = $context;
        }

        if (!empty($extra)) {
            $contextData['Extra'] = $extra;
        }

        $contextJson = json_encode($contextData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return [
            'title' => '📋 Full Context & Extra Data',
            'description' => "```json\n" . Str::limit($contextJson, $this->maxDescriptionLength - 15) . "\n```",
            'color' => 0x95A5A6, // Gray
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.v\Z')
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
