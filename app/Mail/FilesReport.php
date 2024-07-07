<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FilesReport extends Mailable
{
    use Queueable, SerializesModels;

    protected $file,$data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($file = null,$data = ['body' => '','heading' => '','title' => ''])
    {
        // $this->file = $file;
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $email = $this->view('emails.files_report',$this->data);
        // if ($this->file) {
        //     $email->attach($this->file['path'], [
        //         'as' => $this->file['name'],
        //         'mime' => $this->file['mime'],
        //     ]);
        // }

        return $email;
    }
}
