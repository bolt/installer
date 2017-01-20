<?php

namespace Bolt\Installer\Output;

use Symfony\Component\Console\Output\Output;

/**
 * Buffered array output
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class BufferedArrayOutput extends Output
{
    /**
     * @var array
     */
    private $buffer = [];

    /**
     * Empties buffer and returns its content.
     *
     * @return array
     */
    public function fetch()
    {
        $content = $this->buffer;
        $this->buffer = [];

        return $content;
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite($message, $newline)
    {
        if ($newline) {
            $message .= "\n";
        }
        $this->buffer[] = $message;
    }
}
