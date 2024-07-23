<?php

namespace App\Console\Commands;

use App\Imports\NewOrdersImport;
use App\Services\SellerCloudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Webklex\IMAP\Facades\Client;

class NewOrdersExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:new-orders-excel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read new orders from excel file to process';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $file = Storage::disk('local')->path('orderImports/export-3.csv');
        Excel::import(new NewOrdersImport, $file);
        return;
        // read file from local storage
        try {
            $client = Client::account('default'); // Connect to the default account
            $client->connect();

            $folder = $client->getFolder('INBOX');
            $messages = $folder->messages()->from('info@thesuppliesnmore.com')->unseen()->get();
            foreach ($messages as $message) {
                $attachments = $message->getAttachments();
                Log::info('processing email emails new orders');
                foreach ($attachments as $attachment) {
                    Log::info('processing email attachments for new orders');
                    $attachmentName = $attachment->name;
                    $attachmentContent = $attachment->content;
                    \Storage::disk('local')->put('orderImports/' . $attachmentName, $attachmentContent);
                    $file = Storage::disk('local')->path('orderImports/'.$attachmentName);
                    Excel::import(new NewOrdersImport, $file);
                }
                $message->setFlag(['Seen']);
                Log::info('Attachment processed and email marked as read.');
            }
            Log::info('All new orders processed.');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

    }
}
