<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\LlmBudget;
class ExpireAiReservations extends Command {
 protected $signature='ai:expire-reservations';
 protected $description='Fail AI requests older than three minutes and release reserved trial credit';
 public function handle(LlmBudget $budget): int {
  $this->info('Expired requests: '.$budget->expireOverdue());return self::SUCCESS;
 }
}
