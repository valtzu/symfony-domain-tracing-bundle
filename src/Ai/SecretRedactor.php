<?php

namespace Valtzu\DomainTracingBundle\Ai;

/**
 * Replaces secrets in text before it leaves the application: the values of secret environment variables, credentials
 * in URLs and query strings, string literals assigned to secret-like names, well-known token formats and long random
 * looking string literals.
 */
final class SecretRedactor
{
    public const PLACEHOLDER = '[redacted]';

    private const SECRET_NAME = '[A-Za-z0-9_]*(?:secret|passw(?:or)?d|pwd|token|api_?key|apikey|private_?key|credential|auth|dsn|salt|signature|cookie|session)[A-Za-z0-9_]*';
    private const MIN_SECRET_VALUE_LENGTH = 6;

    private const PATTERNS = [
        // Private keys in PEM format
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?(?:-----END [A-Z ]*PRIVATE KEY-----|$)/s' => self::PLACEHOLDER,
        // Credentials in URLs and DSNs: scheme://user:password@host
        '#([a-z][a-z0-9+.-]*://[^\s:/@]*:)[^\s@/]+@#i' => '$1'.self::PLACEHOLDER.'@',
        // Authorization headers
        '/\b(Bearer|Basic|Token)\s+[A-Za-z0-9._~+\/=-]{8,}/' => '$1 '.self::PLACEHOLDER,
        // Well-known token formats: Anthropic and OpenAI, GitHub, AWS access keys, Slack, Stripe, JWT
        '/\b(?:sk-[A-Za-z0-9_-]{16,}|gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|AKIA[0-9A-Z]{16}|xox[abprs]-[A-Za-z0-9-]{10,}|[rs]k_(?:live|test)_[A-Za-z0-9]{10,}|eyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,})\b/' => self::PLACEHOLDER,
        // Secret-like query string parameters
        '/([?&;]'.self::SECRET_NAME.'=)[^&\s#]+/i' => '$1'.self::PLACEHOLDER,
        // String literals assigned to secret-like names: $apiKey = '...', 'password' => '...', TOKEN: "..."
        '/(\b'.self::SECRET_NAME.'[\'"]?\s*(?:=>|=|:)\s*)([\'"])(?:(?!\2).)+\2/i' => '$1$2'.self::PLACEHOLDER.'$2',
        // Long random looking string literals, such as hard-coded keys
        '/([\'"])(?=[A-Za-z0-9+\/_=-]*[0-9])(?=[A-Za-z0-9+\/_=-]*[a-z])(?=[A-Za-z0-9+\/_=-]*[A-Z])[A-Za-z0-9+\/_=-]{24,}\1/' => '$1'.self::PLACEHOLDER.'$1',
    ];

    /** @var array<string, string> secret value => placeholder */
    private array $knownSecrets = [];

    /**
     * @param array<string, mixed>|null $environment defaults to the environment variables of the process
     */
    public function __construct(?array $environment = null)
    {
        foreach ($environment ?? $_SERVER + $_ENV + getenv() as $name => $value) {
            if (\is_string($name) && \is_string($value) && \strlen($value) >= self::MIN_SECRET_VALUE_LENGTH && preg_match('/^'.self::SECRET_NAME.'$/i', $name)) {
                $this->knownSecrets[$value] = '['.$name.' redacted]';
            }
        }
        uksort($this->knownSecrets, static fn (string $a, string $b) => \strlen($b) <=> \strlen($a));
    }

    public static function isSecretName(string $name): bool
    {
        return 1 === preg_match('/^\$?'.self::SECRET_NAME.'$/i', $name);
    }

    public function redact(string $text): string
    {
        $text = strtr($text, $this->knownSecrets);

        return preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text)
            ?? throw new \RuntimeException('Secret redaction failed: '.preg_last_error_msg());
    }
}
