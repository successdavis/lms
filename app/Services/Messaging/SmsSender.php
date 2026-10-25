<?php

namespace App\Services\Messaging;

interface SmsSender
{
    /**
     * @param  string  $to  International format without '+', e.g. 2348031234567
     */
    public function send(string $to, string $message): void;
}
