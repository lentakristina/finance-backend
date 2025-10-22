<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Goal;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class SyncGoalAmounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'goals:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronkan current_amount goals dengan transaksi aktual';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Memulai sinkronisasi goals...');
        $this->newLine();
        
        DB::beginTransaction();
        
        try {
            $goals = Goal::all();
            
            if ($goals->isEmpty()) {
                $this->warn('⚠️  Tidak ada goals yang ditemukan.');
                return 0;
            }
            
            $bar = $this->output->createProgressBar(count($goals));
            $bar->start();
            
            $synced = 0;
            $unchanged = 0;
            
            foreach ($goals as $goal) {
                // Hitung dari transaksi aktual
                $calculated = Transaction::where('goal_id', $goal->id)->sum('amount');
                $oldAmount = $goal->current_amount;
                $diff = $calculated - $oldAmount;
                
                // Update goal
                $goal->current_amount = $calculated;
                $goal->save();
                
                if ($diff != 0) {
                    $synced++;
                    $this->newLine(2);
                    $this->line("📊 Goal: <info>{$goal->name}</info>");
                    $this->line("   Old: Rp " . number_format($oldAmount, 0, ',', '.'));
                    $this->line("   New: Rp " . number_format($calculated, 0, ',', '.'));
                    
                    if ($diff > 0) {
                        $this->line("   <fg=green>▲ +Rp " . number_format($diff, 0, ',', '.') . "</>");
                    } else {
                        $this->line("   <fg=red>▼ -Rp " . number_format(abs($diff), 0, ',', '.') . "</>");
                    }
                } else {
                    $unchanged++;
                }
                
                $bar->advance();
            }
            
            DB::commit();
            $bar->finish();
            
            $this->newLine(2);
            $this->info("✅ Sinkronisasi selesai!");
            $this->newLine();
            $this->line("📈 Total Goals: <comment>" . count($goals) . "</comment>");
            $this->line("🔄 Updated: <info>{$synced}</info>");
            $this->line("✓  Unchanged: <comment>{$unchanged}</comment>");
            
            return 0;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->newLine(2);
            $this->error('❌ Error: ' . $e->getMessage());
            $this->newLine();
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}