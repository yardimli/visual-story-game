<?php
// Append missing defaults only. Protected entries are checked byte-for-byte.
$path = dirname(__DIR__).'/.env';
$text = file_get_contents($path);
preg_match_all('/^(?:DB_[A-Z_]+|DATABASE_URL|OPEN_ROUTER_KEY)=.*$/m', $text, $before);
$defaults = ['APP_NAME' => '"Visual Story Game"', 'APP_ENV' => 'local', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_DEBUG' => 'false', 'APP_URL' => 'http://localhost:8000', 'LOG_CHANNEL' => 'stack', 'SESSION_DRIVER' => 'file', 'CACHE_DRIVER' => 'file', 'QUEUE_CONNECTION' => 'database', 'MAIL_MAILER' => 'log', 'LLM_ALLOWANCE_MICRO_USD' => '1000000'];
foreach ($defaults as $key => $value) {
    if (!preg_match('/^'.preg_quote($key, '/').'=/m', $text)) $text .= "\n$key=$value";
    elseif ($key === 'APP_KEY' && preg_match('/^APP_KEY=\s*$/m', $text)) $text = preg_replace('/^APP_KEY=\s*$/m', "APP_KEY=$value", $text);
}
$text = preg_replace('/^APP_DEBUG=.*$/m', 'APP_DEBUG=false', $text);
preg_match_all('/^(?:DB_[A-Z_]+|DATABASE_URL|OPEN_ROUTER_KEY)=.*$/m', $text, $after);
if ($before[0] !== $after[0]) throw new RuntimeException('Protected settings changed');
file_put_contents($path, rtrim($text)."\n");
echo "Environment defaults ready; database and OpenRouter entries preserved.\n";
