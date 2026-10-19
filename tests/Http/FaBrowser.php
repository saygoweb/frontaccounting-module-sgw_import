<?php

namespace SGW_Import\Tests\Http;

/**
 * FrontAccounting's pages over HTTP, signed in as the CI image's test user
 * (test/test, given every area once the modules are active).
 *
 * It keeps its own cookies rather than a libcurl cookie jar: FrontAccounting
 * marks its session cookie Secure, which PHP's libcurl then never sends to the
 * image's plain-HTTP FA_URL. And it sends a User-Agent, as PHP's libcurl does
 * not: FrontAccounting starts afresh any session without one (session_utils.inc,
 * preventHijacking()).
 *
 * A request that logs an unhandled exception or a fatal error to
 * FrontAccounting's tmp/errors.log fails: thrown once the page has begun, such
 * an error leaves a page that renders to its end with an empty body and no
 * message on it.
 */
final class FaBrowser
{
    private string $url;

    private string $log;

    /** @var array<string, string> */
    private array $cookies = [];

    public function __construct(string $url)
    {
        $this->url = rtrim($url, '/');
        $this->log = getenv('FA_ROOT') . '/tmp/errors.log';
    }

    public function signIn(): void
    {
        $this->get('index.php');
        $this->post('index.php', [
            'user_name_entry_field' => 'test',
            'password' => 'test',
            'company_login_name' => '0',
        ]);
        if (stripos($this->get('index.php'), 'logout') === false) {
            throw new \RuntimeException('FrontAccounting refused test/test');
        }
    }

    public function get(string $path): string
    {
        return $this->request($path, []);
    }

    /**
     * @param array<string, mixed> $fields a \CURLFile among them makes it multipart
     */
    public function post(string $path, array $fields): string
    {
        $multipart = false;
        foreach ($fields as $value) {
            $multipart = $multipart || $value instanceof \CURLFile;
        }

        return $this->request($path, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $multipart ? $fields : http_build_query($fields),
        ]);
    }

    /**
     * @param array<int, mixed> $options
     */
    private function request(string $path, array $options): string
    {
        clearstatcache();
        $logged = is_file($this->log) ? (int) filesize($this->log) : 0;
        $body = $this->exchange($path, $options);
        clearstatcache();
        if (is_file($this->log) && filesize($this->log) > $logged) {
            $errors = preg_grep(
                '/Unhandled exception|Fatal error|Uncaught/',
                explode("\n", (string) file_get_contents($this->log, false, null, $logged))
            );
            if ($errors) {
                throw new \RuntimeException("$path: " . implode("\n", $errors));
            }
        }

        return $body;
    }

    /**
     * @param array<int, mixed> $options
     */
    private function exchange(string $path, array $options): string
    {
        $url = $this->url . '/' . ltrim($path, '/');
        // Redirects by hand, so each hop sends the cookies the last one set.
        for ($hops = 0; $hops < 5; $hops++) {
            $location = null;
            $curl = curl_init($url);
            curl_setopt_array($curl, $options + [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_USERAGENT => 'sgw_import tests',
                CURLOPT_COOKIE => implode('; ', array_map(function (string $name, string $value): string {
                    return "$name=$value";
                }, array_keys($this->cookies), $this->cookies)),
                CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$location): int {
                    if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $header, $m)) {
                        $this->cookies[trim($m[1])] = trim($m[2]);
                    } elseif (preg_match('/^Location:\s*(\S+)/i', $header, $m)) {
                        $location = $m[1];
                    }

                    return strlen($header);
                },
            ]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            if ($body === false || $status >= 400) {
                throw new \RuntimeException("$path: HTTP $status $error");
            }
            if ($status < 300 || $location === null) {
                return (string) $body;
            }
            $url = preg_match('#^https?://#', $location) ? $location : $this->url . '/' . ltrim($location, '/');
            if ($status !== 307 && $status !== 308) {
                $options = [];
            }
        }
        throw new \RuntimeException("$path: too many redirects");
    }
}
