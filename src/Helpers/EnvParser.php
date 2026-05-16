<?php

namespace App\Helpers;

// EnvParser.php
class EnvParser
{
    // Stores parsed environment variables from the loaded .env file.
    private $variables = [];

    /**
     * Load .env file and parse its contents.
     *
     * @param string $path Path to .env file
     * @throws Exception If file not found
     */
    public function load($path)
    {
        if (!file_exists($path)) {
            throw new \Exception(".env file not found at: " . $path);
        }

        // Read file lines ignoring blank lines.
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comment lines or blank lines.
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse each key=value pair and store it.
            $this->parseLine($line);
        }

        return $this;
    }

    /**
     * Parse a single line from the .env file.
     *
     * @param string $line
     */
    private function parseLine($line)
    {
        // Find the first equals sign and ignore invalid lines.
        $equalsPos = strpos($line, '=');
        if ($equalsPos === false) {
            return;
        }

        // Extract the key and raw value parts.
        $key = trim(substr($line, 0, $equalsPos));
        $value = trim(substr($line, $equalsPos + 1));

        // Sanitize the value by stripping quotes and decoding escapes.
        $value = $this->sanitizeValue($value);

        // Save the parsed variable locally and expose it via PHP environment arrays.
        $this->variables[$key] = $value;
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    /**
     * Sanitize value by removing quotes and processing escape characters.
     *
     * @param string $value
     * @return string
     */
    private function sanitizeValue($value)
    {
        // Remove surrounding quotes from string values.
        if (strlen($value) > 1) {
            if (($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
                ($value[0] === "'" && $value[strlen($value) - 1] === "'")
            ) {
                $value = substr($value, 1, -1);
            }
        }

        // Convert common escape sequences into actual characters.
        $value = str_replace('\\n', "\n", $value);
        $value = str_replace('\\r', "\r", $value);
        $value = str_replace('\\t', "\t", $value);

        return $value;
    }

    /**
     * Get a parsed variable value or fallback default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->variables[$key] ?? $default;
    }

    /**
     * Return all parsed environment variables.
     *
     * @return array
     */
    public function all()
    {
        return $this->variables;
    }
}
