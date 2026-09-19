<?php

class Dune_Exception extends Exception
{
    private $error_action;

    /**
     * @param string $message
     * @param int $code
     * @param mixed|null $error_action
     * @param Exception|null $previous
     */
    public function __construct($message, $code = 0,
                                $error_action = null, $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->error_action = $error_action;
    }

    /**
     * @return mixed|null
     */
    public function get_error_action()
    {
        return $this->error_action;
    }
}
