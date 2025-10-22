<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogs extends Command
{
    protected $signature = 'log:clear';
    protected $description = 'Clear Laravel log files';

    public function handle()
    {
        $logPath = storage_path('logs');
        
        if (File::exists($logPath)) {
            $files = File::files($logPath);
            
            foreach ($files as $file) {
                if ($file->getExtension() === 'log') {
                    File::delete($file);
                    $this->info("Deleted: {$file->getFilename()}");
                }
            }
            
            $this->info('All log files have been cleared!');
        } else {
            $this->error('Log directory does not exist!');
        }

        return 0;
    }
}