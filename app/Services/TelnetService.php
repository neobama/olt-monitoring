<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TelnetService
{
    protected $connection;
    protected $timeout = 30;
    protected $prompt = '#';
    protected $bufferSize = 8192;

    public function connect($host, $port, $username, $password)
    {
        try {
            ini_set('max_execution_time', 120);
            set_time_limit(120);
            Log::info("[TELNET] Attempting to connect", [
                'host' => $host,
                'port' => $port
            ]);

            $this->connection = fsockopen($host, $port, $errno, $errstr, $this->timeout);
            
            if (!$this->connection) {
                Log::error("[TELNET] Connection failed", [
                    'host' => $host,
                    'port' => $port,
                    'error' => "$errno: $errstr"
                ]);
                throw new \Exception("Gagal terhubung ke $host:$port - $errstr ($errno)");
            }

            stream_set_timeout($this->connection, $this->timeout);
            stream_set_blocking($this->connection, true);
            
            Log::info("[TELNET] Socket connection established");

            if (!$this->waitFor('Username:')) {
                throw new \Exception("Timeout menunggu prompt username");
            }
            Log::info("[TELNET] Username prompt received");
            
            $this->write($username);
            Log::info("[TELNET] Username sent");
            
            if (!$this->waitFor('Password:')) {
                throw new \Exception("Timeout menunggu prompt password");
            }
            Log::info("[TELNET] Password prompt received");
            
            $this->write($password);
            Log::info("[TELNET] Password sent");
            
            // Tidak perlu validasi prompt command, langsung return sukses
            Log::info("[TELNET] Login sequence completed, skipping prompt validation");
            return true;
        } catch (\Exception $e) {
            Log::error("[TELNET] Connection error", [
                'host' => $host,
                'port' => $port,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->disconnect();
            throw $e;
        }
    }

    public function execute($command, $expectOutput = true)
    {
        Log::debug("[TELNET] execute() called", ['command' => $command, 'expectOutput' => $expectOutput]);
        try {
            Log::info("[TELNET] Executing command", [
                'command' => $command
            ]);

            if ($expectOutput) {
                // Clear any existing output
                $this->read();
            }
            // Send the command
            $this->write($command);
            Log::info("[TELNET] Command sent");
            
            if ($expectOutput) {
                // Read the response
                $output = $this->read();
                
                if (empty($output)) {
                    Log::warning("[TELNET] Empty output received for command", [
                        'command' => $command
                    ]);
                } else {
                    Log::info("[TELNET] Command output received", [
                        'command' => $command,
                        'output_length' => strlen($output),
                        'first_100_chars' => substr($output, 0, 100),
                        'last_100_chars' => substr($output, -100)
                    ]);
                }
                return $output;
            } else {
                // Tidak perlu baca output
                return '';
            }
        } catch (\Exception $e) {
            Log::error("[TELNET] Command execution failed", [
                'command' => $command,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Gagal menjalankan perintah: " . $e->getMessage());
        }
    }

    protected function write($command)
    {
        try {
            Log::debug("[TELNET] Writing to connection", [
                'command' => $command
            ]);

            if (!fwrite($this->connection, $command . "\r\n")) {
                throw new \Exception("Gagal menulis ke koneksi");
            }
            Log::debug("[TELNET] Write successful");
        } catch (\Exception $e) {
            Log::error("[TELNET] Write failed", [
                'command' => $command,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function read()
    {
        try {
            Log::debug("[TELNET] Reading from connection (with prompt detection)");
            $output = '';
            $startTime = time();
            $lastDataTime = microtime(true);
            $maxWait = 2.0;
            $inactivityTimeout = 0.5;
    
            while ((time() - $startTime) < $maxWait) {
                $chunk = fread($this->connection, $this->bufferSize);

                Log::debug("[TELNET] Chunk received", [
                    'chunk_length' => strlen($chunk),
                    'buffer_size' => $this->bufferSize,
                    'total_output_length' => strlen($output)
                ]);                

                if ($chunk !== false && $chunk !== '') {
                    $output .= $chunk;
                    $lastDataTime = microtime(true);
    
                    // Log partial output setiap kali terima chunk
                    Log::debug("[TELNET] Partial output chunk", [
                        'chunk' => $chunk,
                        'current_output_length' => strlen($output)
                    ]);
                    
                    if (strpos($chunk, '--More--') !== false) {
                        Log::debug("[TELNET] '--More--' detected, sending space to continue...");
                        fwrite($this->connection, ' ');
                    }
                    // Deteksi prompt di akhir output (hostname# atau >)
                    if (preg_match('/[#>]\s*$/', trim($output))) {
                        Log::debug("[TELNET] Prompt detected, ending read loop.");
                        break;
                    }
                }
    
                if ((microtime(true) - $lastDataTime) > $inactivityTimeout) {
                    Log::debug("[TELNET] Inactivity timeout reached, ending read loop.");
                    break;
                }
    
                usleep(20000); // kalau error saat retrieving tambahin aja ini
            }
    
            $output = preg_replace('/\r\n|\r|\n/', "\n", $output);
            $output = preg_replace('/\n\s*\n/', "\n", $output);
            $output = trim($output);
    
            Log::debug("[TELNET] Read completed", [
                'output_length' => strlen($output),
                'output' => $output
            ]);
    
            return $output;
        } catch (\Exception $e) {
            Log::error("[TELNET] Read failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function waitFor($prompt)
    {
        try {
            Log::debug("[TELNET] Waiting for prompt", [
                'prompt' => $prompt
            ]);

            $output = '';
            $startTime = time();
            
            while (time() - $startTime < $this->timeout) {
                $chunk = fread($this->connection, $this->bufferSize);
                
                if ($chunk === false) {
                    $info = stream_get_meta_data($this->connection);
                    if ($info['timed_out']) {
                        continue;
                    }
                    break;
                }
                
                if ($chunk !== '') {
                    $output .= $chunk;
                    
                    if (strpos($output, $prompt) !== false) {
                        Log::debug("[TELNET] Prompt found", [
                            'prompt' => $prompt,
                            'output' => $output
                        ]);
                        return true;
                    }
                }
                
                usleep(100000);
            }
            
            Log::error("[TELNET] Prompt not found - timeout", [
                'prompt' => $prompt,
                'output' => $output
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("[TELNET] Wait for prompt failed", [
                'prompt' => $prompt,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function disconnect()
    {
        try {
            Log::info("[TELNET] Disconnecting");
            if ($this->connection) {
                fclose($this->connection);
                $this->connection = null;
                Log::info("[TELNET] Disconnected successfully");
            }
        } catch (\Exception $e) {
            Log::error("[TELNET] Disconnect error", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
} 
