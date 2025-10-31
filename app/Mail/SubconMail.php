<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubconMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * data
     *
     * @var mixed
     */
    protected $data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array
     */
    public function backoff()
    {
        return [5, 15, 30];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $data = (object)$this->data;
        if (isset($data->subject)) {
            $this->subject($data->subject);
        }
        if (isset($data->replyTo)) {
            $this->replyTo($this->data->replyTo);
        }
        if (isset($data->attachment)) {
            $this->attach($data->attachment);
        }
        if (isset($data->view)) {
            $view = $data->view;
        }
        return $this->view($view, (array)$data);
    }
}
