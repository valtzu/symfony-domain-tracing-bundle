<?php

namespace App\Tracing;

/**
 * One connection from the debugger engine, speaking DBGp: https://xdebug.org/docs/dbgp.
 */
final class DbgpConnection
{
    private string $buffer = '';
    private string $pending = '';
    private int $transactionId = 0;

    /**
     * @param resource $socket
     */
    public function __construct(
        public readonly mixed $socket,
    ) {
        stream_set_blocking($socket, false);
    }

    public function send(string $command, string $arguments = '', ?string $data = null): void
    {
        $line = \sprintf('%s -i %d', $command, ++$this->transactionId);
        if ('' !== $arguments) {
            $line .= ' '.$arguments;
        }
        if (null !== $data) {
            $line .= ' -- '.base64_encode($data);
        }

        $this->pending .= $line."\0";
    }

    /**
     * @return list<\SimpleXMLElement>|null null when the engine closed the connection
     */
    public function receive(): ?array
    {
        $chunk = fread($this->socket, 65536);
        if ('' === $chunk || false === $chunk) {
            return feof($this->socket) ? null : [];
        }
        $this->buffer .= $chunk;

        $messages = [];
        while (false !== $lengthEnd = strpos($this->buffer, "\0")) {
            $length = (int) substr($this->buffer, 0, $lengthEnd);
            if (\strlen($this->buffer) < $lengthEnd + 1 + $length + 1) {
                break;
            }
            $messages[] = new \SimpleXMLElement(substr($this->buffer, $lengthEnd + 1, $length));
            $this->buffer = substr($this->buffer, $lengthEnd + 1 + $length + 1);
        }

        return $messages;
    }

    public function close(): void
    {
        $this->flush();
        fclose($this->socket);
    }

    public function flush(): void
    {
        if ('' === $this->pending) {
            return;
        }

        stream_set_blocking($this->socket, true);
        fwrite($this->socket, $this->pending);
        stream_set_blocking($this->socket, false);
        $this->pending = '';
    }
}
